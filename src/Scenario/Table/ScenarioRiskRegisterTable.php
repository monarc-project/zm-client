<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Table;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

/**
 * Reads the compact, ANR-scoped register projection. This deliberately reads
 * current evidence only; detail/history continues to be served by its owning
 * Scenario resources.
 */
final class ScenarioRiskRegisterTable
{
    private Connection $connection;

    public function __construct(EntityManager $entityManager)
    {
        $this->connection = $entityManager->getConnection();
    }

    /** @param array<string, int|string|bool|null> $query @return array<string, mixed> */
    public function list(int $anrId, array $query): array
    {
        [$where, $params] = $this->where($anrId, $query);
        $projection = $this->projection();
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (' . $projection . ') register_row WHERE ' . $where,
            $params
        );
        $sort = $query['sort'] === 'title' ? 'title' : ($query['sort'] === 'lifecycle' ? 'lifecycle' : 'updated_at');
        $direction = strtoupper((string) $query['direction']);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM (' . $projection . ') register_row WHERE ' . $where
            . ' ORDER BY ' . $sort . ' ' . $direction . ', uuid ASC LIMIT ? OFFSET ?',
            [...$params, (int) $query['pageSize'], ((int) $query['page'] - 1) * (int) $query['pageSize']],
            array_merge(array_fill(0, count($params), \PDO::PARAM_STR), [\PDO::PARAM_INT, \PDO::PARAM_INT])
        );

        return [
            'items' => array_map([$this, 'project'], $rows),
            'total' => $count,
            'page' => (int) $query['page'],
            'pageSize' => (int) $query['pageSize'],
            'appliedFilters' => array_filter($query, static fn (mixed $value, string $key): bool => !in_array($key, ['page', 'pageSize'], true) && $value !== null, ARRAY_FILTER_USE_BOTH),
            'supported' => [
                'bands' => ['low', 'medium', 'high', 'critical'],
                'treatmentStates' => ['absent', 'action_required', 'complete'],
                'reviewStates' => ['not_scheduled', 'upcoming', 'due', 'overdue', 'active'],
                'acceptanceStates' => ['absent', 'current', 'decision_required', 'stale'],
                'profileVersions' => $this->profileVersions($anrId),
            ],
            'summary' => $this->summary($where, $params),
        ];
    }

    /** @return array<int, array{uuid:string,label:string}> */
    private function profileVersions(int $anrId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT pv.uuid, CONCAT(COALESCE(p.identifier, \'criteria\'), \' v\', pv.version) AS label '
            . 'FROM scenario_assessments a INNER JOIN scenario_criteria_profile_versions pv ON pv.id = a.profile_version_id '
            . 'LEFT JOIN scenario_criteria_profiles p ON p.id = pv.profile_id WHERE a.anr_id = ? ORDER BY label ASC',
            [$anrId]
        );

        return array_map(static fn (array $row): array => ['uuid' => (string) $row['uuid'], 'label' => (string) $row['label']], $rows);
    }

    /** @param array<int, mixed> $params @return array<string, int> */
    private function summary(string $where, array $params): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS total, '
            . "COALESCE(SUM(treatment_state = 'action_required'), 0) AS treatment_action_required, "
            . "COALESCE(SUM(review_state = 'active'), 0) AS active_review, "
            . "COALESCE(SUM(review_state = 'due'), 0) AS due_review, "
            . "COALESCE(SUM(review_state = 'overdue'), 0) AS overdue_review, "
            . "COALESCE(SUM(acceptance_state IN ('decision_required', 'stale')), 0) AS decision_required, "
            . "COALESCE(SUM(current_band = 'low'), 0) AS band_low, "
            . "COALESCE(SUM(current_band = 'medium'), 0) AS band_medium, "
            . "COALESCE(SUM(current_band = 'high'), 0) AS band_high, "
            . "COALESCE(SUM(current_band = 'critical'), 0) AS band_critical "
            . 'FROM (' . $this->projection() . ') register_row WHERE ' . $where,
            $params
        );

        return [
            'total' => (int) ($row['total'] ?? 0),
            'treatmentActionRequired' => (int) ($row['treatment_action_required'] ?? 0),
            'activeReview' => (int) ($row['active_review'] ?? 0),
            'dueReview' => (int) ($row['due_review'] ?? 0),
            'overdueReview' => (int) ($row['overdue_review'] ?? 0),
            'decisionRequired' => (int) ($row['decision_required'] ?? 0),
            'bandLow' => (int) ($row['band_low'] ?? 0),
            'bandMedium' => (int) ($row['band_medium'] ?? 0),
            'bandHigh' => (int) ($row['band_high'] ?? 0),
            'bandCritical' => (int) ($row['band_critical'] ?? 0),
        ];
    }

    /** @return array{0:string,1:array<int, mixed>} */
    private function where(int $anrId, array $query): array
    {
        $where = ['anr_id = ?'];
        $params = [$anrId];
        $map = [
            'lifecycle' => 'lifecycle', 'completeness' => 'completeness', 'band' => 'current_band',
            'profileVersion' => 'profile_version_uuid', 'treatmentState' => 'treatment_state',
            'reviewState' => 'review_state', 'acceptanceState' => 'acceptance_state',
        ];
        foreach ($map as $field => $column) {
            if ($query[$field] !== null) {
                $where[] = $column . ' = ?';
                $params[] = $query[$field];
            }
        }
        if ($query['triggerOccurred'] !== null) {
            $where[] = 'trigger_occurred = ?';
            $params[] = $query['triggerOccurred'] ? 1 : 0;
        }
        if ($query['text'] !== null) {
            $where[] = '(title LIKE ? OR narrative LIKE ? OR source_title LIKE ? OR event_title LIKE ? OR consequence_title LIKE ?)';
            foreach (range(1, 5) as $_) {
                $params[] = '%' . $query['text'] . '%';
            }
        }
        if ($query['subject'] !== null) {
            $where[] = 'subjects LIKE ?';
            $params[] = '%' . $query['subject'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    private function projection(): string
    {
        return <<<'SQL'
SELECT s.anr_id, s.uuid, s.title, s.description AS narrative, s.updated_at,
       a.lifecycle,
       CASE WHEN rs.id IS NOT NULL AND ev.id IS NOT NULL AND co.id IS NOT NULL AND ec.id IS NOT NULL THEN 'complete' ELSE 'incomplete' END AS completeness,
       rs.title AS source_title, ev.title AS event_title, co.title AS consequence_title,
       (SELECT GROUP_CONCAT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(sl.reference_snapshot, '$.label')) SEPARATOR ', ') FROM scenario_subject_links sl WHERE sl.risk_scenario_id = s.id) AS subjects,
       JSON_UNQUOTE(JSON_EXTRACT(current_assessment.calculation_snapshot, '$.band')) AS current_band,
       JSON_UNQUOTE(JSON_EXTRACT(inherent_assessment.calculation_snapshot, '$.band')) AS inherent_band,
       JSON_UNQUOTE(JSON_EXTRACT(residual_assessment.calculation_snapshot, '$.band')) AS proposed_residual_band,
       JSON_UNQUOTE(JSON_EXTRACT(current_assessment.calculation_snapshot, '$.explanation')) AS current_explanation,
       pv.uuid AS profile_version_uuid, CONCAT(COALESCE(cp.identifier, 'criteria'), ' v', pv.version) AS profile_version,
       COALESCE(treatment.state, 'absent') AS treatment_state, treatment.owner_name AS treatment_owner, treatment.due_at AS treatment_due_at, treatment.progress AS recommendation_progress,
       CASE WHEN EXISTS(SELECT 1 FROM scenario_review_cases arc INNER JOIN scenario_review_case_scopes arcs ON arcs.review_case_id = arc.id WHERE arc.anr_id = s.anr_id AND arcs.risk_scenario_id = s.id AND arc.status IN ('open', 'in_progress', 'awaiting_decision')) THEN 'active'
            WHEN a.next_review_at IS NULL THEN 'not_scheduled'
            WHEN a.next_review_at < UTC_TIMESTAMP() THEN 'overdue'
            WHEN DATE(a.next_review_at) = UTC_DATE() THEN 'due' ELSE 'upcoming' END AS review_state,
       (SELECT arc.uuid FROM scenario_review_cases arc INNER JOIN scenario_review_case_scopes arcs ON arcs.review_case_id = arc.id WHERE arc.anr_id = s.anr_id AND arcs.risk_scenario_id = s.id AND arc.status IN ('open', 'in_progress', 'awaiting_decision') ORDER BY arc.created_at DESC, arc.id DESC LIMIT 1) AS review_case_uuid,
       EXISTS(SELECT 1 FROM scenario_review_trigger_occurrences tro INNER JOIN scenario_review_case_scopes trcs ON trcs.review_case_id = tro.review_case_id WHERE tro.anr_id = s.anr_id AND trcs.risk_scenario_id = s.id) AS trigger_occurred,
       CASE WHEN acceptance.id IS NOT NULL AND acceptance.assessment_id = residual_assessment.id THEN 'current'
            WHEN acceptance.id IS NOT NULL THEN 'stale'
            WHEN residual_assessment.id IS NOT NULL THEN 'decision_required' ELSE 'absent' END AS acceptance_state
FROM scenario_risk_scenarios s
INNER JOIN scenario_analyses a ON a.id = s.scenario_analysis_id
LEFT JOIN scenario_risk_sources rs ON rs.risk_scenario_id = s.id
LEFT JOIN scenario_events ev ON ev.id = (SELECT e.id FROM scenario_events e WHERE e.risk_scenario_id = s.id ORDER BY e.created_at ASC, e.id ASC LIMIT 1)
LEFT JOIN scenario_consequences co ON co.id = (SELECT c.id FROM scenario_consequences c WHERE c.risk_scenario_id = s.id ORDER BY c.created_at ASC, c.id ASC LIMIT 1)
LEFT JOIN scenario_event_consequences ec ON ec.id = (SELECT sec.id FROM scenario_event_consequences sec WHERE sec.risk_scenario_id = s.id ORDER BY sec.id ASC LIMIT 1)
LEFT JOIN scenario_assessments current_assessment ON current_assessment.id = (SELECT ca.id FROM scenario_assessments ca WHERE ca.risk_scenario_id = s.id AND ca.assessment_type = 'current' ORDER BY ca.created_at DESC, ca.id DESC LIMIT 1)
LEFT JOIN scenario_assessments inherent_assessment ON inherent_assessment.id = (SELECT ia.id FROM scenario_assessments ia WHERE ia.risk_scenario_id = s.id AND ia.assessment_type = 'inherent' ORDER BY ia.created_at DESC, ia.id DESC LIMIT 1)
LEFT JOIN scenario_assessments residual_assessment ON residual_assessment.id = (SELECT ra.id FROM scenario_assessments ra WHERE ra.risk_scenario_id = s.id AND ra.assessment_type = 'proposed_residual' ORDER BY ra.created_at DESC, ra.id DESC LIMIT 1)
LEFT JOIN scenario_criteria_profile_versions pv ON pv.id = current_assessment.profile_version_id
LEFT JOIN scenario_criteria_profiles cp ON cp.id = pv.profile_id
LEFT JOIN (SELECT t.risk_scenario_id, CASE WHEN COUNT(*) = 0 THEN 'absent' WHEN SUM(t.status NOT IN ('completed', 'cancelled')) > 0 OR SUM(CASE WHEN i.id IS NULL THEN 0 WHEN i.status NOT IN ('implemented', 'not_applicable') THEN 1 ELSE 0 END) > 0 THEN 'action_required' ELSE 'complete' END AS state, MAX(t.due_at) AS due_at, MAX(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))) AS owner_name, CONCAT(SUM(CASE WHEN i.status IN ('implemented', 'not_applicable') THEN 1 ELSE 0 END), '/', COUNT(i.id)) AS progress FROM scenario_treatments t LEFT JOIN scenario_treatment_recommendation_implementations i ON i.treatment_id = t.id LEFT JOIN users u ON u.id = t.owner_id GROUP BY t.risk_scenario_id) treatment ON treatment.risk_scenario_id = s.id
LEFT JOIN scenario_acceptance_decisions acceptance ON acceptance.id = (SELECT ad.id FROM scenario_acceptance_decisions ad WHERE ad.risk_scenario_id = s.id ORDER BY ad.created_at DESC, ad.id DESC LIMIT 1)
SQL;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function project(array $row): array
    {
        return [
            'uuid' => (string) $row['uuid'], 'title' => (string) $row['title'], 'narrative' => $row['narrative'],
            'lifecycle' => (string) $row['lifecycle'], 'completeness' => (string) $row['completeness'],
            'context' => ['source' => $row['source_title'], 'event' => $row['event_title'], 'consequence' => $row['consequence_title'], 'subjects' => $row['subjects']],
            'evaluations' => ['current' => $row['current_band'], 'inherent' => $row['inherent_band'], 'proposedResidual' => $row['proposed_residual_band'], 'explanation' => $row['current_explanation'], 'profileVersionUuid' => $row['profile_version_uuid'], 'profileVersion' => $row['profile_version']],
            'treatment' => ['state' => $row['treatment_state'] ?? 'absent', 'owner' => $row['treatment_owner'], 'dueAt' => $row['treatment_due_at'], 'recommendationProgress' => $row['recommendation_progress']],
            'review' => ['state' => (string) $row['review_state'], 'caseUuid' => $row['review_case_uuid'], 'triggerOccurred' => (bool) $row['trigger_occurred']],
            'acceptance' => ['state' => (string) $row['acceptance_state']], 'updatedAt' => (string) $row['updated_at'],
        ];
    }
}
