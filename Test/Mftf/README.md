# MFTF tests

Standard Magento MFTF layout (`Test/`, `ActionGroup/`, `Data/`, `Helper/`, `Section/`) per the
[Adobe MFTF getting-started guide](https://developer.adobe.com/commerce/testing/functional-testing-framework/getting-started).

Full scenario inventory — what's covered, by which test — is [SCENARIOS.md](SCENARIOS.md), not here.

Run via `.github/workflows/mftf.yml` (`workflow_dispatch`), matrix-split by group (`campaign`, `campaign2`,
`campaign3`, `segment`, `tracking`, `dashboard`, `lifecycle`). See `AGENTS.md` for known CI-only pitfalls before
adding a new test.
