import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
export default defineConfig({
    root: ".",
    base: "./",
    plugins: [react()],
    build: {
        outDir: "dist-demo",
        emptyOutDir: true,
        rollupOptions: { input: "demo/index.html" },
    },
});
