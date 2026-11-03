import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import fs from "node:fs";
const lock = JSON.parse(
    fs.readFileSync("resources/templates/catalog.lock.json", "utf8"),
);
const templates = [];
for (const directory of fs
    .readdirSync("resources/templates", { withFileTypes: true })
    .filter((entry) => entry.isDirectory())) {
    for (const file of fs
        .readdirSync(`resources/templates/${directory.name}`)
        .filter((name) => name.endsWith(".json"))) {
        const manifest = JSON.parse(
            fs.readFileSync(
                `resources/templates/${directory.name}/${file}`,
                "utf8",
            ),
        );
        const { files, ...metadata } = manifest;
        templates.push({
            ...metadata,
            digest: lock[`${manifest.id}@${manifest.version}`],
            file_count: Object.keys(files).length,
            file_paths: Object.keys(files),
            compatible: true,
        });
    }
}
export default defineConfig({
    publicDir: false,
    define: { __QOPENPANEL_TEMPLATES__: JSON.stringify(templates) },
    root: ".",
    base: "./",
    plugins: [react()],
    build: {
        outDir: "dist-demo",
        emptyOutDir: true,
        rollupOptions: { input: "demo/index.html" },
    },
});
