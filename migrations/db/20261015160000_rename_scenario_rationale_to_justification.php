<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/** Temporary local upgrade for the Scenario terminology rename. */
final class RenameScenarioRationaleToJustification extends AbstractMigration
{
    public function up(): void
    {
        $auditEvents = $this->table('scenario_audit_events');
        if ($auditEvents->hasColumn('rationale')) {
            $this->execute('ALTER TABLE scenario_audit_events CHANGE rationale justification TEXT NULL');
        }

        $this->execute("UPDATE scenario_criteria_drafts SET acceptance_rules = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(acceptance_rules, '$.low', 'justification_required_for_retain'),
                    '$.medium', 'justification_required_for_retain'
                ),
                '$.high', 'justification_required_for_retain'
            ),
            '$.critical', 'justification_required_for_retain'
        ) WHERE CAST(acceptance_rules AS CHAR) LIKE '%rationale_required_for_retain%'");
        $this->execute("UPDATE scenario_criteria_profile_versions SET payload = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(payload, '$.acceptanceRules.low', 'justification_required_for_retain'),
                    '$.acceptanceRules.medium', 'justification_required_for_retain'
                ),
                '$.acceptanceRules.high', 'justification_required_for_retain'
            ),
            '$.acceptanceRules.critical', 'justification_required_for_retain'
        ) WHERE CAST(payload AS CHAR) LIKE '%rationale_required_for_retain%'");
        $this->execute("UPDATE scenario_analysis_criteria_profiles SET profile_snapshot = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(
                        profile_snapshot,
                        '$.payload.acceptanceRules.low',
                        'justification_required_for_retain'
                    ),
                    '$.payload.acceptanceRules.medium', 'justification_required_for_retain'
                ),
                '$.payload.acceptanceRules.high', 'justification_required_for_retain'
            ),
            '$.payload.acceptanceRules.critical', 'justification_required_for_retain'
        ) WHERE CAST(profile_snapshot AS CHAR) LIKE '%rationale_required_for_retain%'");
        $this->execute("UPDATE scenario_assessments
            SET calculation_snapshot = JSON_REMOVE(
                JSON_SET(
                    calculation_snapshot,
                    '$.inputs.justification',
                    JSON_EXTRACT(calculation_snapshot, '$.inputs.rationale')
                ),
                '$.inputs.rationale'
            )
            WHERE JSON_CONTAINS_PATH(calculation_snapshot, 'one', '$.inputs.rationale')");
        $this->execute("UPDATE scenario_assessments
            SET calculation_snapshot = JSON_REPLACE(
                calculation_snapshot,
                '$.threshold',
                'justification_required_for_retain'
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(calculation_snapshot, '$.threshold')) = 'rationale_required_for_retain'");
    }

    public function down(): void
    {
        $auditEvents = $this->table('scenario_audit_events');
        if ($auditEvents->hasColumn('justification')) {
            $this->execute('ALTER TABLE scenario_audit_events CHANGE justification rationale TEXT NULL');
        }

        $this->execute("UPDATE scenario_criteria_drafts SET acceptance_rules = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(acceptance_rules, '$.low', 'rationale_required_for_retain'),
                    '$.medium', 'rationale_required_for_retain'
                ),
                '$.high', 'rationale_required_for_retain'
            ),
            '$.critical', 'rationale_required_for_retain'
        ) WHERE CAST(acceptance_rules AS CHAR) LIKE '%justification_required_for_retain%'");
        $this->execute("UPDATE scenario_criteria_profile_versions SET payload = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(payload, '$.acceptanceRules.low', 'rationale_required_for_retain'),
                    '$.acceptanceRules.medium', 'rationale_required_for_retain'
                ),
                '$.acceptanceRules.high', 'rationale_required_for_retain'
            ),
            '$.acceptanceRules.critical', 'rationale_required_for_retain'
        ) WHERE CAST(payload AS CHAR) LIKE '%justification_required_for_retain%'");
        $this->execute("UPDATE scenario_analysis_criteria_profiles SET profile_snapshot = JSON_REPLACE(
            JSON_REPLACE(
                JSON_REPLACE(
                    JSON_REPLACE(profile_snapshot, '$.payload.acceptanceRules.low', 'rationale_required_for_retain'),
                    '$.payload.acceptanceRules.medium', 'rationale_required_for_retain'
                ),
                '$.payload.acceptanceRules.high', 'rationale_required_for_retain'
            ),
            '$.payload.acceptanceRules.critical', 'rationale_required_for_retain'
        ) WHERE CAST(profile_snapshot AS CHAR) LIKE '%justification_required_for_retain%'");
        $this->execute("UPDATE scenario_assessments
            SET calculation_snapshot = JSON_REMOVE(
                JSON_SET(
                    calculation_snapshot,
                    '$.inputs.rationale',
                    JSON_EXTRACT(calculation_snapshot, '$.inputs.justification')
                ),
                '$.inputs.justification'
            )
            WHERE JSON_CONTAINS_PATH(calculation_snapshot, 'one', '$.inputs.justification')");
        $this->execute("UPDATE scenario_assessments
            SET calculation_snapshot = JSON_REPLACE(
                calculation_snapshot,
                '$.threshold',
                'rationale_required_for_retain'
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(calculation_snapshot, '$.threshold'))
                = 'justification_required_for_retain'");
    }
}
