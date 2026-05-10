# SourceBans++ — manual maintenance targets.
#
# Routine dev workflow goes through ./sbpp.sh (panel + DB + tests). This
# Makefile carries one-shot targets that don't fit the wrapper's
# lifecycle / quality-gate model — currently just the manual sync of
# vendored cross-repo schema lock files (#1126).

# Where the cf-analytics repo publishes the canonical schema-1 lock
# file. The panel vendors a byte-identical copy at
# `web/includes/Telemetry/schema-1.lock.json`; the parity tests
# (TelemetrySchemaParityTest, TelemetryReadmeParityTest) gate the
# result so a sync that adds a typed slot fails the build until the
# matching extractor + README bullet land.
TELEMETRY_SCHEMA_URL := https://raw.githubusercontent.com/sbpp/cf-analytics/main/schema/1.lock.json
TELEMETRY_SCHEMA_DST := web/includes/Telemetry/schema-1.lock.json

.PHONY: sync-telemetry-schema

# Manual invocation only — no scheduled auto-PR workflow in v1. Run
# this when picking up cf-analytics changes; commit the result alongside
# any matching extractor / README updates.
sync-telemetry-schema:
	@echo "Syncing $(TELEMETRY_SCHEMA_DST) from $(TELEMETRY_SCHEMA_URL)…"
	@curl --fail --location --silent --show-error \
		--output "$(TELEMETRY_SCHEMA_DST).new" \
		"$(TELEMETRY_SCHEMA_URL)"
	@mv "$(TELEMETRY_SCHEMA_DST).new" "$(TELEMETRY_SCHEMA_DST)"
	@echo "Done. Run ./sbpp.sh test to gate the new shape against extractor + README parity."
