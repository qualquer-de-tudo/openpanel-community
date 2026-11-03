import fs from "node:fs";
import { execFileSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import path from "node:path";

export function assertImmutable(previous, current) {
    for (const [key, digest] of Object.entries(previous)) {
        if (current[key] !== digest)
            throw new Error(
                `Published template cannot be changed or removed: ${key}`,
            );
    }
}
if (
    process.argv[1] &&
    path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    const reference = process.env.TEMPLATE_BASE_REF;
    if (!reference || /^0+$/.test(reference)) {
        console.log("No previous release catalog to compare.");
    } else {
        if (
            !/^[A-Za-z0-9][A-Za-z0-9_./-]*$/.test(reference) ||
            reference.includes("..")
        )
            throw new Error("Invalid base reference");
        execFileSync(
            "git",
            ["rev-parse", "--verify", `${reference}^{commit}`],
            { stdio: "pipe" },
        );
        const previousPath = `${reference}:resources/templates/catalog.lock.json`;
        const previousFile = execFileSync(
            "git",
            [
                "ls-tree",
                "--name-only",
                reference,
                "--",
                "resources/templates/catalog.lock.json",
            ],
            { encoding: "utf8" },
        ).trim();
        const previous = previousFile
            ? JSON.parse(
                  execFileSync("git", ["show", previousPath], {
                      encoding: "utf8",
                  }),
              )
            : {};
        const current = JSON.parse(
            fs.readFileSync("resources/templates/catalog.lock.json", "utf8"),
        );
        assertImmutable(previous, current);
        console.log("Published template versions are immutable.");
    }
}
