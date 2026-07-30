const fs = require('fs');
const path = '/Users/huynhtronghieu/Documents/WORK Hiếu/quan-ly-phong-kham/modules/medical/chiro_history_v2.php';
let lines = fs.readFileSync(path, 'utf8').split('\n');

// The pain map starts at `<!-- SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO -->`
let startIdx = lines.findIndex(l => l.includes('<!-- SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO -->'));
// We find the ending `</div>` for this block.
// Wait, the block starts at `startIdx` and ends at `</div>` before `</div>` (line 307). Let's count divs.
// Actually, it's easier to just find `<!-- CLOSE PART 3 -->` and insert it there.
let endPart3Idx = lines.findIndex(l => l.includes('<!-- CLOSE PART 3 -->'));

if (startIdx !== -1 && endPart3Idx !== -1) {
    // Find the end of the pain map block by searching for the `</div>` that closes it.
    // By looking at the code, the block ends at line 306.
    let endIdx = startIdx;
    let divCount = 0;
    let foundFirstDiv = false;
    for (let i = startIdx; i < lines.length; i++) {
        if (lines[i].includes('<div')) {
            divCount += (lines[i].match(/<div/g) || []).length;
            foundFirstDiv = true;
        }
        if (lines[i].includes('</div')) {
            divCount -= (lines[i].match(/<\/div/g) || []).length;
        }
        if (foundFirstDiv && divCount <= 0) {
            endIdx = i;
            break;
        }
    }
    
    // Extract the block
    let painMapBlock = lines.splice(startIdx, endIdx - startIdx + 1);
    
    // Recalculate endPart3Idx because we removed lines before it
    endPart3Idx = lines.findIndex(l => l.includes('<!-- CLOSE PART 3 -->'));
    
    // Insert after CLOSE PART 3
    lines.splice(endPart3Idx + 1, 0, ...painMapBlock);
    
    fs.writeFileSync(path, lines.join('\n'));
    console.log('Moved successfully!');
} else {
    console.log('Could not find markers', startIdx, endPart3Idx);
}
