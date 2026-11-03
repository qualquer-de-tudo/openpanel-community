import fs from "node:fs";
import path from "node:path";
const [id, version, author] = process.argv.slice(2);
if (
    !id ||
    !version ||
    !author ||
    !/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(id) ||
    id.length > 64 ||
    !/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/.test(version)
)
    throw new Error(
        'Usage: npm run templates:new -- my-template 1.0.0 "Author"',
    );
const root = path.resolve("resources/templates");
const directory = path.resolve(root, id);
if (!directory.startsWith(root + path.sep))
    throw new Error("Invalid template directory");
const example = JSON.parse(
    fs.readFileSync(path.join(root, "static-site", "1.0.0.json"), "utf8"),
);
example.id = id;
example.version = version;
example.author = author;
for (const locale of Object.keys(example.name)) example.name[locale] = id;
fs.mkdirSync(directory, { recursive: true });
fs.writeFileSync(
    path.join(directory, version + ".json"),
    JSON.stringify(example, null, 2) + "\n",
    { flag: "wx" },
);
console.log(
    `Created resources/templates/${id}/${version}.json. Edit metadata and files, then run npm run templates:lock and npm run templates:check.`,
);
