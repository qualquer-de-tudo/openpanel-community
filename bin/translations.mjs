import fs from "node:fs";
import path from "node:path";
import prettier from "prettier";
const registryPath = "resources/data/locales.json";
const registry = JSON.parse(fs.readFileSync(registryPath));
const basePath = "lang/pt_BR.json";
const base = JSON.parse(fs.readFileSync(basePath));
const [action = "check", code, name, tag] = process.argv.slice(2);
const parameters = (text) =>
    [...text.matchAll(/:([A-Za-z_][A-Za-z0-9_]*)/g)]
        .map((match) => match[1])
        .sort()
        .join(",");
if (action === "add") {
    if (
        !code ||
        !name ||
        !tag ||
        !/^[a-z]{2}(?:_[A-Z]{2})?$/.test(code) ||
        registry.some((locale) => locale.code === code)
    )
        throw new Error(
            "Usage: npm run translations:add -- de Deutsch de-DE (new locale only)",
        );
    if (fs.existsSync(`lang/${code}.json`))
        throw new Error("Translation file already exists");
    fs.writeFileSync(
        `lang/${code}.json`,
        JSON.stringify(
            Object.fromEntries(Object.keys(base).map((key) => [key, ""])),
            null,
            2,
        ) + "\n",
    );
    fs.mkdirSync(`lang/${code}`, { recursive: true });
    fs.writeFileSync(
        `lang/${code}/validation.php`,
        "<?php\n\nreturn require __DIR__.'/../validation.php';\n",
    );
    registry.push({ code, name, tag, direction: "ltr" });
    fs.writeFileSync(registryPath, JSON.stringify(registry, null, 2) + "\n");
    console.log(
        `Added ${code}: fill lang/${code}.json, then run npm run translations:check.`,
    );
} else if (["check", "sync", "status"].includes(action)) {
    let errors = 0;
    for (const locale of registry) {
        const file = `lang/${locale.code}.json`;
        let messages = {};
        try {
            messages = JSON.parse(fs.readFileSync(file));
        } catch {
            console.error(`${locale.code}: invalid or missing JSON`);
            errors++;
            continue;
        }
        if (action === "sync") {
            for (const key of Object.keys(base))
                if (!Object.hasOwn(messages, key)) messages[key] = "";
            fs.writeFileSync(file, JSON.stringify(messages, null, 2) + "\n");
        }
        let missing = 0;
        for (const [key, value] of Object.entries(base)) {
            if (typeof messages[key] !== "string" || !messages[key].trim()) {
                missing++;
                continue;
            }
            if (parameters(messages[key]) !== parameters(value)) {
                console.error(`${locale.code}: placeholder mismatch: ${key}`);
                errors++;
            }
        }
        const extra = Object.keys(messages).filter(
            (key) => !Object.hasOwn(base, key),
        );
        console.log(
            `${locale.code}: ${Object.keys(base).length - missing}/${Object.keys(base).length} translated, ${extra.length} extra keys`,
        );
        errors += missing + extra.length;
    }
    if (action === "check") {
        for (const file of [
            "resources/js/app.jsx",
            "resources/js/Templates.jsx",
        ]) {
            const source = fs.readFileSync(file, "utf8");
            const { ast } = await prettier.__debug.parse(source, {
                parser: "babel",
            });
            const brands = new Set([
                "open",
                "panel",
                "community",
                "O",
                "· PHP",
                "OpenPanel Community",
                "Laravel + React + Inertia",
                "MB",
                "v",
                "· v",
                "SHA-256:",
            ]);
            function inspect(node, parent) {
                if (!node || typeof node !== "object") return;
                if (node.type === "JSXText") {
                    const value = node.value.trim().replace(/\s+/g, " ");
                    if (/\p{L}/u.test(value) && !brands.has(value)) {
                        console.error(`Unlocalized JSX text: ${value}`);
                        errors++;
                    }
                }
                if (
                    node.type === "CallExpression" &&
                    node.callee?.type === "Identifier" &&
                    node.callee.name === "t" &&
                    node.arguments[0]?.type === "StringLiteral" &&
                    !Object.hasOwn(base, node.arguments[0].value)
                ) {
                    console.error(
                        `Missing source key: ${node.arguments[0].value}`,
                    );
                    errors++;
                }
                for (const [key, value] of Object.entries(node)) {
                    if (["loc", "tokens", "comments", "extra"].includes(key))
                        continue;
                    if (Array.isArray(value))
                        value.forEach((child) => inspect(child, node));
                    else if (value && typeof value === "object")
                        inspect(value, node);
                }
            }
            inspect(ast, null);
        }
        if (errors) {
            console.error(`Translation check failed: ${errors} issue(s).`);
            process.exitCode = 1;
        } else console.log("Translation coverage and interpolation: OK.");
    }
} else throw new Error("Commands: check, sync, status, add");
