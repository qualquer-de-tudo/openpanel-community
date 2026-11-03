import test from "node:test";
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { readdirSync } from "node:fs";

test("distribution contains the module and no tracked Laravel application skeleton", () => {
    const files = execFileSync("git", ["ls-files", "--cached"], { encoding: "utf8" }).trim().split("\n");
    const skeleton = /^(app\/|bootstrap\/|public\/|storage\/|database\/(migrations|factories|seeders)\/|artisan$|\.env\.example$|routes\/(web|console)\.php$|config\/(?!qopenpanel\.php$))/;
    assert.deepEqual(files.filter((file) => skeleton.test(file)), []);
});

test("published frontend contains only the module manifest, JavaScript and CSS", () => {
    assert.deepEqual(readdirSync("resources/dist").sort(), ["assets", "manifest.json"]);
    assert.ok(readdirSync("resources/dist/assets").every((name) => /\.(js|css)$/.test(name)));
});
