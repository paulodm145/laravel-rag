const CATEGORY_LABELS = {
    habilitacao_juridica: 'Habilitação jurídica',
    regularidade_fiscal_trabalhista: 'Regularidade fiscal e trabalhista',
    qualificacao_tecnica: 'Qualificação técnica',
    qualificacao_economico_financeira: 'Qualificação econômico-financeira',
    declaracoes: 'Declarações',
    outros: 'Outros',
};

function safeFilename(name) {
    return name
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\.pdf$/i, '')
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();
}

export async function exportChecklistPdf(documentMeta, documents, warnings) {
    const { jsPDF } = await import('jspdf');
    const pdf = new jsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 18;
    const contentWidth = pageWidth - margin * 2;
    const bottomLimit = pageHeight - 20;
    let y = 20;

    const addPage = () => {
        pdf.addPage();
        y = 20;
    };

    const ensureSpace = (height) => {
        if (y + height > bottomLimit) {
            addPage();
        }
    };

    const write = (text, options = {}) => {
        const {
            size = 10,
            style = 'normal',
            color = [35, 35, 35],
            indent = 0,
            gap = 2,
        } = options;
        const lines = pdf.splitTextToSize(String(text), contentWidth - indent);
        const lineHeight = size * 0.42;

        ensureSpace(lines.length * lineHeight + gap);
        pdf.setFont('helvetica', style);
        pdf.setFontSize(size);
        pdf.setTextColor(...color);
        pdf.text(lines, margin + indent, y);
        y += lines.length * lineHeight + gap;
    };

    pdf.setProperties({
        title: `Checklist de habilitação - ${documentMeta.name}`,
        subject: 'Documentos de habilitação identificados no edital',
        creator: 'RAG Laravel',
    });

    write('Checklist de habilitação', { size: 18, style: 'bold', gap: 3 });
    write(documentMeta.name, { size: 12, style: 'bold', color: [47, 95, 208] });
    write(
        `${documentMeta.pages} páginas | ${documents.length} documentos identificados`,
        { color: [100, 100, 100], gap: 6 }
    );

    if (warnings.length > 0) {
        write('Pontos que exigem conferência manual', { size: 12, style: 'bold' });

        warnings.forEach((warning) => {
            write(`- ${warning}`, { indent: 3, color: [105, 80, 20] });
        });

        y += 3;
    }

    Object.entries(CATEGORY_LABELS).forEach(([category, label]) => {
        const items = documents.filter((item) => {
            const itemCategory = CATEGORY_LABELS[item.category] ? item.category : 'outros';

            return itemCategory === category;
        });

        if (items.length === 0) {
            return;
        }

        ensureSpace(18);
        write(label.toUpperCase(), {
            size: 10,
            style: 'bold',
            color: [100, 100, 100],
            gap: 4,
        });

        items.forEach((item) => {
            ensureSpace(24);
            write(`[ ] ${item.name}`, { size: 11, style: 'bold' });

            const tags = [
                item.requirement === 'condicional' ? 'Condicional' : 'Obrigatório',
                `Confiança: ${item.confidence}`,
                item.page ? `Página: ${item.page}` : null,
            ].filter(Boolean);

            write(tags.join(' | '), { size: 9, color: [85, 85, 85], indent: 4 });

            if (item.notes) {
                write(item.notes, { size: 9, indent: 4 });
            }

            write(`Trecho: “${item.evidence}”`, {
                size: 9,
                style: 'italic',
                color: [70, 70, 70],
                indent: 4,
                gap: 5,
            });
        });
    });

    write(
        'Resultado gerado automaticamente. Confira as cláusulas citadas e leia o edital integralmente antes de tomar qualquer decisão.',
        { size: 8, color: [100, 100, 100], gap: 0 }
    );

    const totalPages = pdf.getNumberOfPages();

    for (let page = 1; page <= totalPages; page += 1) {
        pdf.setPage(page);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(8);
        pdf.setTextColor(120, 120, 120);
        pdf.text(
            `Página ${page} de ${totalPages}`,
            pageWidth - margin,
            pageHeight - 10,
            { align: 'right' }
        );
    }

    const filename = safeFilename(documentMeta.name) || 'edital';
    pdf.save(`checklist-${filename}.pdf`);
}
