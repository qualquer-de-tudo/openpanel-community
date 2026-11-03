import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
export default defineConfig({plugins:[react()],build:{outDir:"resources/dist",emptyOutDir:true,manifest:"manifest.json",rollupOptions:{input:"resources/js/app.jsx"}}});
