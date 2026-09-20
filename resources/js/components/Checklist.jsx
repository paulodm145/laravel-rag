import { useState } from 'react';
import { exportChecklistPdf } from '../utils/exportChecklistPdf';

const CATEGORY_LABELS = {
    habilitacao_juridica: 'Habilitação jurídica',
    regularidade_fiscal_trabalhista: 'Regularidade fiscal e trabalhista',
    qualificacao_tecnica: 'Qualificação técnica',
    qualificacao_economico_financeira: 'Qualificação econômico-financeira',
    declaracoes: 'Declarações',
    outros: 'Outros',
};

const CATEGORY_ORDER = Object.keys(CATEGORY_LABELS);

function groupByCategory(documents) {
    const groups = new Map();

    documents.forEach((item, index) => {
        const key = CATEGORY_LABELS[item.category] ? item.category : 'outros';

        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key).push({ ...item, index });
    });

    return CATEGORY_ORDER.filter((key) => groups.has(key)).map((key) => ({
        key,
        label: CATEGORY_LABELS[key],
        items: groups.get(key),
    }));
}

export function Checklist({ document: meta, documents, warnings, onReset }) {
    const [checked, setChecked] = useState(() => new Set());
    const [expanded, setExpanded] = useState(() => new Set());

    const toggleChecked = (index) => {
        setChecked((previous) => {
            const next = new Set(previous);
            next.has(index) ? next.delete(index) : next.add(index);

            return next;
        });
    };

    const toggleExpanded = (index) => {
        setExpanded((previous) => {
            const next = new Set(previous);
            next.has(index) ? next.delete(index) : next.add(index);

            return next;
        });
    };

    const groups = groupByCategory(documents);

    return (
        <section className="result">
            <header className="result-header">
                <div>
                    <h2>{meta.name}</h2>
                    <p className="result-meta">
                        {meta.pages} páginas · {documents.length} documentos identificados ·{' '}
                        {checked.size} marcados
                    </p>
                </div>

                <div className="result-actions">
                    <button
                        type="button"
                        onClick={() => exportChecklistPdf(meta, documents, warnings)}
                    >
                        Exportar PDF
                    </button>
                    <button type="button" className="ghost" onClick={onReset}>
                        Analisar outro
                    </button>
                </div>
            </header>

            {warnings.length > 0 && (
                <div className="warnings">
                    <p className="warnings-title">Pontos que exigem conferência manual</p>
                    <ul>
                        {warnings.map((warning, index) => (
                            <li key={index}>{warning}</li>
                        ))}
                    </ul>
                </div>
            )}

            {documents.length === 0 && (
                <p className="empty">
                    O agente não encontrou base suficiente no edital para montar a lista.
                </p>
            )}

            {groups.map((group) => (
                <div className="group" key={group.key}>
                    <h3>{group.label}</h3>

                    <ul className="items">
                        {group.items.map((item) => (
                            <li
                                key={item.index}
                                className={checked.has(item.index) ? 'is-checked' : ''}
                            >
                                <label className="item-main">
                                    <input
                                        type="checkbox"
                                        checked={checked.has(item.index)}
                                        onChange={() => toggleChecked(item.index)}
                                    />
                                    <span className="item-name">{item.name}</span>
                                </label>

                                <div className="item-tags">
                                    {item.requirement === 'condicional' && (
                                        <span className="tag">condicional</span>
                                    )}
                                    {item.confidence !== 'alta' && (
                                        <span className="tag tag-warn">
                                            confiança {item.confidence}
                                        </span>
                                    )}
                                    {item.page && <span className="tag tag-page">pág. {item.page}</span>}
                                </div>

                                {item.notes && <p className="item-notes">{item.notes}</p>}

                                <button
                                    type="button"
                                    className="link"
                                    onClick={() => toggleExpanded(item.index)}
                                >
                                    {expanded.has(item.index)
                                        ? 'Ocultar trecho do edital'
                                        : 'Ver trecho do edital'}
                                </button>

                                {expanded.has(item.index) && (
                                    <blockquote className="item-evidence">{item.evidence}</blockquote>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}

            <p className="disclaimer">
                Resultado gerado automaticamente a partir do texto do edital. A lista não
                substitui a leitura integral do instrumento convocatório nem a conferência
                das cláusulas citadas.
            </p>
        </section>
    );
}
