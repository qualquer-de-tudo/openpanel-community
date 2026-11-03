import fs from "node:fs";
const html = fs
    .readFileSync("dist-demo/demo/index.html", "utf8")
    .replaceAll("../assets/", "./assets/");
fs.writeFileSync("dist-demo/index.html", html);
fs.writeFileSync("dist-demo/.nojekyll", "");
