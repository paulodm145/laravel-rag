# Gabarito de habilitação

Lista levantada manualmente no Pregão Eletrônico 006/2026 de Vargem Bonita/SC, a partir
do Anexo II e do corpo do edital.

Serve para medir o recall do sistema na Parte 3 e fechar a série com um número real em
vez de "funcionou bem". **Não alterar este arquivo para fazer o sistema parecer melhor.**
Se o sistema encontrar uma exigência legítima que não está aqui, o gabarito é que estava
incompleto: corrija com a citação da cláusula que comprova.

Alvo: 18 itens e 3 observações.

## Habilitação jurídica (Anexo II, item 1)

| # | Documento | Cláusula |
| --- | --- | --- |
| 1 | Registro comercial, no caso de empresa individual | anexo_ii/1.a |
| 2 | Ato constitutivo, estatuto ou contrato social em vigor devidamente registrado, acompanhado dos documentos de eleição dos administradores no caso de sociedade por ações | anexo_ii/1.b |
| 3 | Inscrição do ato constitutivo com prova de diretoria em exercício, para sociedades civis | anexo_ii/1.c |
| 4 | Decreto de autorização e ato de registro ou autorização para funcionamento, para empresa ou sociedade estrangeira | anexo_ii/1.d |

Os itens 1 a 4 são alternativos entre si, conforme a natureza jurídica do licitante.

## Regularidade fiscal e trabalhista (Anexo II, item 2)

| # | Documento | Cláusula |
| --- | --- | --- |
| 5 | Prova de inscrição no CNPJ, atualizada | anexo_ii/2.a |
| 6 | Certidão negativa de débitos relativos a tributos federais e à dívida ativa da União | anexo_ii/2.b |
| 7 | Prova de regularidade para com a Fazenda Estadual | anexo_ii/2.c |
| 8 | Prova de regularidade para com a Fazenda Municipal da sede | anexo_ii/2.d |
| 9 | Prova de regularidade do FGTS | anexo_ii/2.e |
| 10 | Certidão negativa de débitos trabalhistas (CNDT) | anexo_ii/2.f |
| 11 | Certidão negativa de falência e recuperação judicial, expedida há no máximo 60 dias | anexo_ii/2.g |

## Qualificação técnica (Anexo II, item 3)

| # | Documento | Cláusula |
| --- | --- | --- |
| 12 | Atestado de capacidade técnica fornecido por pessoa jurídica de direito público ou privado | anexo_ii/3.a |

## Declarações (Anexo II, item 4)

| # | Documento | Cláusula |
| --- | --- | --- |
| 13 | Declaração de não emprego de menor, nos termos do art. 68, VI, da Lei 14.133/2021 | anexo_ii/4.a |
| 14 | Declaração de informações complementares, conforme o modelo de carta de preposto do Anexo IV | anexo_ii/4.b |
| 15 | Declaração de inexistência de fatos impeditivos e de não ter sido declarada inidônea | anexo_ii/4.c |
| 16 | Declaração de que conhece o edital na íntegra e se submete às suas condições | anexo_ii/4.d |

## Exigências fora do Anexo II

Estão no corpo do edital. São o principal motivo para não recuperar apenas o anexo.

| # | Documento | Cláusula |
| --- | --- | --- |
| 17 | Declaração de que atende aos requisitos de habilitação | corpo/7.5 |
| 18 | Declaração de cumprimento das exigências de reserva de cargos para pessoa com deficiência e reabilitado da Previdência Social | corpo/7.6 |
| 19 | Declaração de que a proposta compreende a integralidade dos custos trabalhistas | corpo/7.7 |

O item 19 é condição de classificação da proposta, não de habilitação. O sistema pode
listá-lo separadamente, mas não deve omiti-lo.

## Observações que o sistema deveria capturar

| # | Observação | Cláusula |
| --- | --- | --- |
| A | A documentação de habilitação jurídica, fiscal, social, trabalhista e econômico-financeira pode ser substituída pelo registro cadastral no SICAF | corpo/7.1.1 |
| B | Para microempresa e empresa de pequeno porte, a regularidade fiscal e trabalhista só é exigida para efeito de contratação, não como condição de participação | corpo/7.14 |
| C | Conflito: o Termo de Referência exige balanço patrimonial e demonstrações contábeis do último exercício para qualificação econômico-financeira, mas o Anexo II não pede balanço, apenas a certidão de falência. O sistema deve sinalizar o conflito e citar as duas cláusulas, não escolher uma. | anexo_i/8.3.4 vs anexo_ii/2.g |

## Como medir

Na Parte 3, a medição é só da recuperação, sem o modelo generativo:

1. Rodar as consultas de recuperação, uma por categoria de habilitação.
2. Para cada item do gabarito, verificar se a cláusula de origem aparece entre os trechos
   recuperados.
3. Recall = itens encontrados ÷ 19.

Na Parte 3, a mesma medição sobre a resposta final do agente, que mede recuperação e
geração juntas. A diferença entre as duas indica se o erro está na busca ou no modelo.
