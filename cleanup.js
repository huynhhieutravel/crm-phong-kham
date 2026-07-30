const fs = require('fs');

const path = '/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/chiro_history_v2.php';
const lines = fs.readFileSync(path, 'utf8').split('\n');

const newLines = [];

for (let i = 0; i < lines.length; i++) {
    const lineNum = i + 1;
    
    // Delete 252 to 337
    if (lineNum >= 252 && lineNum <= 337) continue;
    
    // Delete 396 to 417 (Suspected Causes)
    if (lineNum >= 396 && lineNum <= 417) continue;
    
    // Delete 423 to 428 (General Notes in Intervention)
    if (lineNum >= 423 && lineNum <= 428) continue;
    
    // Delete 506 to 694 (Disease Groups to Additional Notes)
    if (lineNum >= 506 && lineNum <= 694) continue;
    
    newLines.push(lines[i]);
}

fs.writeFileSync(path, newLines.join('\n'));
console.log("Cleanup complete!");
