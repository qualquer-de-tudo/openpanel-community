import { test } from "node:test";
import assert from "node:assert/strict";
import http from "node:http";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { spawn } from "node:child_process";
import { once } from "node:events";

async function runBridge(configuration, messages) {
    const directory = await fs.mkdtemp(path.join(os.tmpdir(), "qpm-bridge-"));
    const file = path.join(directory, "client.json");
    await fs.writeFile(file, JSON.stringify(configuration), { mode: 0o600 });
    try {
        const child = spawn(process.execPath, ["bin/mcp-bridge.mjs", file], {
            cwd: process.cwd(),
            env: { ...process.env, QOPENPANEL_LOCALE: "pt_BR" },
            stdio: ["pipe", "pipe", "pipe"],
        });
        let stdout = "",
            stderr = "";
        child.stdout.setEncoding("utf8").on("data", (data) => (stdout += data));
        child.stderr.setEncoding("utf8").on("data", (data) => (stderr += data));
        child.stdin.end(
            messages.map((message) => JSON.stringify(message)).join("\n") +
                "\n",
        );
        const [code] = await once(child, "close");
        return { code, stdout, stderr };
    } finally {
        const resolvedDirectory = await fs.realpath(directory);
        const resolvedTemp = await fs.realpath(os.tmpdir());
        assert.ok(resolvedDirectory.startsWith(resolvedTemp + path.sep));
        await fs.rm(resolvedDirectory, { recursive: true, force: true });
    }
}

test("bridge keeps transport credential out of protocol output and sends initialization notifications", async () => {
    const credential = "qpm_" + "b".repeat(96),
        received = [];
    const server = http.createServer(async (req, res) => {
        assert.equal(req.headers.authorization, `Bearer ${credential}`);
        let body = "";
        for await (const chunk of req) body += chunk;
        const request = JSON.parse(body);
        received.push(request.method);
        if (!Object.hasOwn(request, "id")) {
            res.writeHead(202).end();
            return;
        }
        res.setHeader("Content-Type", "application/json");
        res.end(
            JSON.stringify({
                jsonrpc: "2.0",
                id: request.id,
                result:
                    request.method === "initialize"
                        ? { protocolVersion: "2025-11-25" }
                        : { tools: [] },
            }),
        );
    });
    server.listen(0, "127.0.0.1");
    await once(server, "listening");
    try {
        const result = await runBridge(
            {
                endpoint: `http://127.0.0.1:${server.address().port}/mcp/panel`,
                token: credential,
            },
            [
                { jsonrpc: "2.0", id: 1, method: "initialize", params: {} },
                { jsonrpc: "2.0", method: "notifications/initialized" },
                { jsonrpc: "2.0", id: 2, method: "tools/list" },
            ],
        );
        assert.equal(result.code, 0);
        assert.deepEqual(received, [
            "initialize",
            "notifications/initialized",
            "tools/list",
        ]);
        assert.equal(result.stdout.trim().split("\n").length, 2);
        assert.equal(result.stderr, "");
        assert.ok(!result.stdout.includes(credential));
    } finally {
        server.close();
    }
});

test("bridge refuses redirects and never relays provider bodies or credentials", async () => {
    const credential = "qpm_" + "c".repeat(96);
    const server = http.createServer((req, res) => {
        res.writeHead(302, { Location: "http://127.0.0.1:1/steal" }).end(
            credential,
        );
    });
    server.listen(0, "127.0.0.1");
    await once(server, "listening");
    try {
        const result = await runBridge(
            {
                endpoint: `http://127.0.0.1:${server.address().port}/mcp/panel`,
                token: credential,
            },
            [{ jsonrpc: "2.0", id: 1, method: "tools/list" }],
        );
        assert.equal(JSON.parse(result.stdout).error.code, -32603);
        assert.ok(!result.stdout.includes(credential));
        assert.ok(!result.stderr.includes(credential));
    } finally {
        server.close();
    }
});

test("bridge refuses non-loopback HTTP before making any request", async () => {
    const result = await runBridge(
        {
            endpoint: "http://example.com/mcp/panel",
            token: "qpm_" + "d".repeat(96),
        },
        [],
    );
    assert.equal(result.code, 1);
    assert.equal(result.stdout, "");
    assert.match(result.stderr, /configuração privada inválida/);
});
