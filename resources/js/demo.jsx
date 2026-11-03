import React from "react";
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { Panel } from "./app.jsx";
import catalog from "../data/catalog.json";
const resources = [
    {
        id: 1,
        kind: "sites",
        name: "meu-portfolio",
        engine: "React",
        domain: "portfolio.example.com",
        disk_mb: 512,
        status: "planned",
    },
    {
        id: 2,
        kind: "sites",
        name: "minha-api",
        engine: "Laravel",
        domain: "api.example.com",
        disk_mb: 1024,
        status: "planned",
    },
    {
        id: 3,
        kind: "databases",
        name: "app-postgres",
        engine: "PostgreSQL",
        domain: null,
        disk_mb: 1024,
        status: "planned",
    },
    {
        id: 4,
        kind: "sites",
        name: "stack-docker",
        engine: "Docker Compose",
        domain: null,
        disk_mb: 512,
        status: "planned",
    },
    {
        id: 5,
        kind: "security",
        name: "antivirus-uploads",
        engine: "ClamAV",
        domain: null,
        disk_mb: 256,
        status: "planned",
    },
];
createInertiaApp({
    page: {
        component: "Panel",
        props: {
            demo: true,
            resources,
            catalog,
            account: {
                disk_mb: 20480,
                memory_mb: 4096,
                project_limit: 10,
                database_limit: 5,
            },
            user: { name: "Visitante", email: "visitante@example.com" },
            server: {
                hostname: "servidor-demo",
                platform: "Linux (ilustrativo)",
                php: "8.5",
                agent: false,
            },
        },
        url: window.location.pathname,
        version: "demo",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => Panel,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
