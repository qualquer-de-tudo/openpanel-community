import { test } from "node:test";
import assert from "node:assert/strict";
import { assertImmutable } from "../../bin/templates-policy.mjs";
test("new template versions preserve older release digests", () => {
    assert.doesNotThrow(() =>
        assertImmutable(
            { "site@1.0.0": "original" },
            { "site@1.0.0": "original", "site@1.1.0": "new" },
        ),
    );
});
test("changing or deleting a released template is refused", () => {
    assert.throws(() =>
        assertImmutable(
            { "site@1.0.0": "original" },
            { "site@1.0.0": "changed" },
        ),
    );
    assert.throws(() => assertImmutable({ "site@1.0.0": "original" }, {}));
});
