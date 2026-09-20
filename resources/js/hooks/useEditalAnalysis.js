import { useCallback, useState } from 'react';
import axios from 'axios';

axios.defaults.headers.common['X-CSRF-TOKEN'] =
    document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Envia o PDF, espera a indexação e a análise, e devolve a lista de documentos.
 * A requisição é longa de propósito: o backend indexa e analisa em um passo só.
 */
export function useEditalAnalysis() {
    const [status, setStatus] = useState('idle');
    const [result, setResult] = useState(null);
    const [error, setError] = useState(null);

    const analyze = useCallback(async (file) => {
        setStatus('loading');
        setError(null);
        setResult(null);

        const payload = new FormData();
        payload.append('edital', file);

        try {
            const { data } = await axios.post('/editais/analyze', payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: 300000,
            });

            setResult(data);
            setStatus('done');
        } catch (exception) {
            setError(
                exception.response?.data?.message ??
                    'Não foi possível analisar o edital. Verifique os logs da aplicação.'
            );
            setStatus('error');
        }
    }, []);

    const reset = useCallback(() => {
        setStatus('idle');
        setResult(null);
        setError(null);
    }, []);

    return { status, result, error, analyze, reset };
}
