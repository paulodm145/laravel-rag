import { useCallback, useRef, useState } from 'react';

export function DropZone({ onFile, disabled }) {
    const [isOver, setIsOver] = useState(false);
    const inputRef = useRef(null);

    const handleDrop = useCallback(
        (event) => {
            event.preventDefault();
            setIsOver(false);

            if (disabled) {
                return;
            }

            const file = event.dataTransfer.files?.[0];

            if (file) {
                onFile(file);
            }
        },
        [disabled, onFile]
    );

    return (
        <div
            className={`dropzone ${isOver ? 'is-over' : ''} ${disabled ? 'is-disabled' : ''}`}
            onDragOver={(event) => {
                event.preventDefault();
                setIsOver(true);
            }}
            onDragLeave={() => setIsOver(false)}
            onDrop={handleDrop}
            onClick={() => !disabled && inputRef.current?.click()}
        >
            <input
                ref={inputRef}
                type="file"
                accept="application/pdf"
                hidden
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        onFile(file);
                    }

                    event.target.value = '';
                }}
            />

            <p className="dropzone-title">Arraste o edital em PDF para cá</p>
            <p className="dropzone-hint">ou clique para escolher um arquivo</p>
        </div>
    );
}
