# AI Principles

Wayfindr treats AI as a tool for better support, not as a product identity.

AI should help agents and contributors move faster when the task genuinely
benefits from language understanding, summarization, classification, retrieval,
or generation. It should not replace clear support workflows, consent, privacy,
or boring deterministic code.

## Product Principles

- Use AI to assist people, not to hide product gaps.
- Keep agents in control of customer-facing replies and support decisions.
- Make AI optional for self-hosters and safe to leave unconfigured.
- Prefer small AI features with obvious value over broad assistant surfaces.
- Use the minimum necessary support context for each prompt.
- Label generated suggestions clearly.
- Keep provider choices configurable.
- Test AI behavior with fakes, fixtures, and structured outputs.

## Good Early Fits

- Reply drafts that an agent reviews before sending.
- Conversation summaries for handoff or ticket creation.
- Suggested ticket titles, priorities, tags, and next steps.
- Knowledge-base answer suggestions once knowledge sources exist.
- Development helpers that expose safe local project context through MCP.

## Poor Fits For Now

- Autonomous customer replies.
- Unbounded analysis of all customer conversations.
- Sending masked, private, or sensitive data to providers by default.
- Features that fail when no model provider is configured.
- Marketing copy that says "AI-powered" without explaining the concrete value.

When in doubt, build the normal workflow first. Add AI only when it makes that
workflow more useful, clearer, faster, or easier to operate.
