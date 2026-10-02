# Scenario terminology migration inventory

SRA-16 adopts the ISO/IEC 27005:2022 term **risk scenario** for the
source/cause/event/consequence aggregate inside a Scenario analysis.

## Active contract

- The protected FrontOffice API uses `risk-scenarios` and
  `risk-scenario-references` routes.
- PHP domain services, tables, controllers, validators, exceptions and DI
  entries use `RiskScenario` / `risk_scenario` terminology.
- New audit actions and payload labels use `risk_scenario`.

## Persistence inventory

Fresh installations receive `scenario_risk_scenarios` and `risk_scenario_id`
identifiers from the original create migrations. The temporary
`20261002100000_rename_scenario_risk_stories_to_risk_scenarios.php` migration
renames former persistence identifiers in an already-migrated local database,
preserving existing UUIDs, revisions, template snapshots, assessments, links
and audit-event rows. It is a no-op for the fresh schema and can be removed
from local development after the affected databases have been migrated or
rebuilt.

Historic append-only `scenario_audit_events` data is never rewritten. No
legacy audit evidence exists in the current deployment, so no terminology
adapter is retained.

The SRA-16 local validation harness also checks that the former reference
endpoint is retired rather than retained as a second mutation path.
