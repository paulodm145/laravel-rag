import { createRoot } from 'react-dom/client';
import { Checklist } from './components/Checklist';
import { DropZone } from './components/DropZone';
import { useEditalAnalysis } from './hooks/useEditalAnalysis';

function App() {
    const { status, result, error, analyze, reset } = useEditalAnalysis();

    return (
        <div className="page">
            <header className="page-header">
                <h1>Checklist de habilitação</h1>
                <p>
                    Envie um edital de licitação em PDF e receba a lista de documentos que
                    ele exige, com o trecho e a página de origem de cada exigência.
                </p>
            </header>

            <main>
                {status === 'idle' && <DropZone onFile={analyze} />}

                {status === 'loading' && (
                    <div className="loading">
                        <div className="spinner" aria-hidden="true" />
                        <p className="loading-title">Processando o edital</p>
                        <p className="loading-hint">
                            Extraindo o texto, gerando os vetores e consultando o agente.
                            Pode levar algum tempo em documentos longos.
                        </p>
                    </div>
                )}

                {status === 'error' && (
                    <div className="error">
                        <p>{error}</p>
                        <button type="button" onClick={reset}>
                            Tentar de novo
                        </button>
                    </div>
                )}

                {status === 'done' && result && (
                    <Checklist
                        document={result.document}
                        documents={result.documents}
                        warnings={result.warnings}
                        onReset={reset}
                    />
                )}
            </main>

            <footer className="page-footer">
                <p>
                    <strong>Projeto de estudo.</strong> Esta aplicação foi construída para
                    demonstrar o uso do Laravel AI SDK em um fluxo de geração aumentada por
                    recuperação. Não é um produto, não tem garantia de completude ou
                    exatidão e não deve ser usada como base para decisão em processo
                    licitatório.
                </p>
            </footer>
        </div>
    );
}

createRoot(document.getElementById('app')).render(<App />);
