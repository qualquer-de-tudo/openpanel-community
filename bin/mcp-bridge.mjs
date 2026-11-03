#!/usr/bin/env node
import fs from "node:fs";
import readline from "node:readline";

let configuration;
try {
    const path = process.argv[2];
    const stat = fs.lstatSync(path);
    if (
        !stat.isFile() ||
        stat.isSymbolicLink() ||
        stat.size > 8192 ||
        (process.platform !== "win32" &&
            ((stat.mode & 0o077) !== 0 || stat.uid !== process.getuid()))
    )
        throw new Error();
    configuration = JSON.parse(fs.readFileSync(path, "utf8"));
    const endpoint = new URL(configuration.endpoint);
    if (
        !/^qpm_[a-f0-9]{96}$/.test(configuration.token) ||
        endpoint.username ||
        endpoint.password ||
        endpoint.search ||
        endpoint.hash ||
        endpoint.pathname !== "/mcp/panel" ||
        (endpoint.protocol !== "https:" &&
            !(
                endpoint.protocol === "http:" &&
                ["localhost", "127.0.0.1", "[::1]"].includes(endpoint.hostname)
            ))
    )
        throw new Error();
} catch {
    process.stderr.write(
        "QOpenPanel: configuração privada inválida ou permissões insuficientes.\n",
    );
    process.exit(1);
}

let protocol = "2025-11-25";
const input = readline.createInterface({
    input: process.stdin,
    crlfDelay: Infinity,
});
for await (const line of input) {
    let message;
    try {
        if (Buffer.byteLength(line) > 262144) throw new Error();
        message = JSON.parse(line);
        if (
            message.jsonrpc !== "2.0" ||
            typeof message.method !== "string" ||
            Array.isArray(message)
        )
            throw new Error();
        const headers = {
            "Content-Type": "application/json",
            Accept: "application/json, text/event-stream",
            Authorization: `Bearer ${configuration.token}`,
            "MCP-Protocol-Version": protocol,
            "Mcp-Method": message.method,
        };
        if (message.params?.name) headers["Mcp-Name"] = message.params.name;
        const response = await fetch(configuration.endpoint, {
            method: "POST",
            headers,
            body: JSON.stringify(message),
            redirect: "error",
            signal: AbortSignal.timeout(90000),
        });
        if (response.status === 202 || !Object.hasOwn(message, "id")) continue;
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (result.result?.protocolVersion)
            protocol = result.result.protocolVersion;
        process.stdout.write(JSON.stringify(result) + "\n");
    } catch {
        if (message && Object.hasOwn(message, "id"))
            process.stdout.write(
                JSON.stringify({
                    jsonrpc: "2.0",
                    id: message.id,
                    error: {
                        code: -32603,
                        message:
                            "QOpenPanel: operação recusada, acesso expirado ou servidor indisponível.",
                    },
                }) + "\n",
            );
        else
            process.stderr.write(
                "QOpenPanel: mensagem inválida ou operação recusada.\n",
            );
    }
}
