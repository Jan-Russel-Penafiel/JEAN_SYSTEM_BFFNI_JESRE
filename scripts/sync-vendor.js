const fs = require('fs');
const path = require('path');

const projectRoot = path.resolve(__dirname, '..');

const mappings = [
  {
    source: 'node_modules/chart.js/dist/chart.umd.js',
    target: 'assets/vendor/chartjs/chart.umd.js',
  },
  {
    source: 'node_modules/jspdf/dist/jspdf.umd.min.js',
    target: 'assets/vendor/jspdf/jspdf.umd.min.js',
  },
  {
    source: 'node_modules/qrcodejs2/qrcode.min.js',
    target: 'assets/vendor/qrcodejs/qrcode.min.js',
  },
];

function syncOneFile(sourceRelativePath, targetRelativePath) {
  const sourcePath = path.join(projectRoot, sourceRelativePath);
  const targetPath = path.join(projectRoot, targetRelativePath);

  if (!fs.existsSync(sourcePath)) {
    throw new Error(`Missing source file: ${sourceRelativePath}`);
  }

  fs.mkdirSync(path.dirname(targetPath), { recursive: true });
  fs.copyFileSync(sourcePath, targetPath);

  const size = fs.statSync(targetPath).size;
  console.log(`Synced ${targetRelativePath} (${size} bytes)`);
}

for (const mapping of mappings) {
  syncOneFile(mapping.source, mapping.target);
}

console.log('Vendor sync completed.');
