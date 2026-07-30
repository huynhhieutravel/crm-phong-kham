/**
 * Medical Marking Tool
 * Handles interactive pain point marking on anatomical diagrams.
 */

class MedicalMarking {
    constructor(canvasId, inputId, imageUrl, readOnly = false) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.inputId = inputId;
        this.imageUrl = imageUrl;
        this.readOnly = readOnly;
        
        this.markers = [];
        this.currentIntensity = 'M3';
        this.isEraser = false;
        
        this.colors = {
            'M1': '#38bdf8', 'M2': '#4ade80', 'M3': '#fbbf24', 'M4': '#fb923c', 'M5': '#ef4444'
        };

        this.init();
    }

    async init() {
        // Load initial data from hidden input
        const input = document.getElementById(this.inputId);
        if (input && input.value) {
            try {
                let data = JSON.parse(input.value);
                // Handle potential double encoding
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                this.markers = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error("Failed to parse markers", e);
            }
        }

        // Load background image
        this.bgImage = new Image();
        this.bgImage.src = this.imageUrl;
        this.bgImage.onload = () => {
            // Adjust canvas dimensions to match image aspect ratio
            this.canvas.width = this.bgImage.naturalWidth;
            this.canvas.height = this.bgImage.naturalHeight;
            this.draw();
        };

        if (!this.readOnly) {
            this.canvas.addEventListener('click', (e) => this.handleClick(e));
            this.setupControls();
        }
    }

    setupControls() {
        // Intensity selectors (Pain Points)
        document.querySelectorAll('.intensity-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.currentIntensity = e.currentTarget.dataset.intensity;
                this.isEraser = false;
                this.currentTool = 'marker';
                
                // UI feedback
                document.querySelectorAll('.intensity-btn, .tool-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const markerBtn = document.getElementById('tool-marker');
                if (markerBtn) markerBtn.classList.add('active');
            });
        });

        // Special Tools (Surgery, Fracture)
        const surgeryBtn = document.getElementById('tool-surgery');
        if (surgeryBtn) {
            surgeryBtn.addEventListener('click', () => {
                this.currentTool = 'surgery';
                this.isEraser = false;
                this.updateActiveTool(surgeryBtn);
            });
        }

        const fractureBtn = document.getElementById('tool-fracture');
        if (fractureBtn) {
            fractureBtn.addEventListener('click', () => {
                this.currentTool = 'fracture';
                this.isEraser = false;
                this.updateActiveTool(fractureBtn);
            });
        }

        const markerBtn = document.getElementById('tool-marker');
        if (markerBtn) {
            markerBtn.addEventListener('click', () => {
                this.currentTool = 'marker';
                this.isEraser = false;
                this.updateActiveTool(markerBtn);
                // Also activate default intensity M5 if none active
                if (!document.querySelector('.intensity-btn.active')) {
                    const m5 = document.querySelector('[data-intensity="M5"]');
                    if (m5) m5.classList.add('active');
                }
            });
        }

        // Eraser/Clear
        const eraserBtn = document.getElementById('marker-eraser') || document.getElementById('tool-eraser');
        if (eraserBtn) {
            eraserBtn.addEventListener('click', () => {
                this.isEraser = true;
                this.updateActiveTool(eraserBtn);
            });
        }

        const clearBtn = document.getElementById('marker-clear') || document.getElementById('tool-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (confirm('Xóa toàn bộ đánh dấu?')) {
                    this.markers = [];
                    this.updateInput();
                    this.draw();
                }
            });
        }
    }

    updateActiveTool(activeBtn) {
        document.querySelectorAll('.tool-btn, .intensity-btn').forEach(b => b.classList.remove('active'));
        if (activeBtn) activeBtn.classList.add('active');
    }

    handleClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        const y = (e.clientY - rect.top) / rect.height;

        if (this.isEraser) {
            this.markers = this.markers.filter(m => {
                const dist = Math.sqrt(Math.pow(m.x - x, 2) + Math.pow(m.y - y, 2));
                return dist > 0.03; // Eraser radius
            });
        } else {
            const newMarker = { x, y, type: this.currentTool || 'marker' };
            if (newMarker.type === 'marker') {
                newMarker.intensity = this.currentIntensity;
            }
            this.markers.push(newMarker);
        }

        this.updateInput();
        this.draw();
    }

    updateInput() {
        const input = document.getElementById(this.inputId);
        if (input) {
            input.value = JSON.stringify(this.markers);
        }
    }

    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw background
        if (this.bgImage) {
            this.ctx.drawImage(this.bgImage, 0, 0, this.canvas.width, this.canvas.height);
        }

        // Draw markers
        this.markers.forEach(m => {
            const px = m.x * this.canvas.width;
            const py = m.y * this.canvas.height;

            if (m.type === 'surgery') {
                // Draw yellow 'O'
                this.ctx.beginPath();
                this.ctx.arc(px, py, 10, 0, Math.PI * 2);
                this.ctx.strokeStyle = '#f59e0b';
                this.ctx.lineWidth = 3;
                this.ctx.stroke();
                this.ctx.closePath();
            } else if (m.type === 'fracture') {
                // Draw red 'X'
                const size = 8;
                this.ctx.beginPath();
                this.ctx.moveTo(px - size, py - size);
                this.ctx.lineTo(px + size, py + size);
                this.ctx.moveTo(px + size, py - size);
                this.ctx.lineTo(px - size, py + size);
                this.ctx.strokeStyle = '#ef4444';
                this.ctx.lineWidth = 3;
                this.ctx.stroke();
                this.ctx.closePath();
            } else {
                // Default: Colored dot
                this.ctx.beginPath();
                this.ctx.arc(px, py, 8, 0, Math.PI * 2);
                this.ctx.fillStyle = this.colors[m.intensity] || '#60a5fa';
                this.ctx.shadowBlur = 4;
                this.ctx.shadowColor = 'rgba(0,0,0,0.2)';
                this.ctx.fill();
                this.ctx.strokeStyle = '#fff';
                this.ctx.lineWidth = 2;
                this.ctx.stroke();
                this.ctx.shadowBlur = 0;
                this.ctx.closePath();
            }
        });
    }
}
