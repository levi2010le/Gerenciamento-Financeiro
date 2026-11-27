import React, { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Feather } from '@expo/vector-icons';
import * as Print from 'expo-print';
import * as Sharing from 'expo-sharing';

import styles from '../styles';
import { C } from '../theme';

function obterDescricao(item) {
  return (
    item.desc ||
    item.ds_entrada ||
    item.ds_saida ||
    item.descricao ||
    'Sem descrição'
  );
}

function obterData(item) {
  return (
    item.data ||
    item.dt_entrada ||
    item.dt_saida ||
    item.dataFormatada ||
    'Data não informada'
  );
}

function obterConta(item) {
  return (
    item.conta ||
    item.nm_banco ||
    item.nomeBanco ||
    '—'
  );
}

function obterPagamento(item) {
  const pagamento =
    item.formaPagamento ||
    item.tp_pagamento ||
    item.metodo ||
    '';

  const nomesPagamento = {
    PIX: 'Pix',
    pix: 'Pix',
    CARTAO_DEBITO: 'Cartão de débito',
    CARTAO_CREDITO: 'Cartão de crédito',
    cartao: 'Cartão',
    BOLETO: 'Boleto',
    DINHEIRO: 'Dinheiro',
    dinheiro: 'Dinheiro',
    OUTROS: 'Outros',
  };

  return nomesPagamento[pagamento] || pagamento || '—';
}

function obterValor(item) {
  return (
    item.valor ??
    item.vl_entrada ??
    item.vl_saida ??
    0
  );
}

function formatarData(data) {
  if (!data) return 'Data não informada';

  const texto = String(data);
  const correspondencia = texto.match(
    /^(\d{4})-(\d{2})-(\d{2})(.*)$/
  );

  if (!correspondencia) return texto;

  const [, ano, mes, dia, restante] = correspondencia;
  return `${dia}/${mes}/${ano}${restante}`;
}

function escaparHTML(valor) {
  return String(valor ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function criarLinhasPDF(lista, tipo, formatMoney) {
  if (lista.length === 0) {
    return `
      <tr>
        <td colspan="5" class="empty">
          Nenhuma ${tipo} encontrada.
        </td>
      </tr>
    `;
  }

  return lista
    .map((item) => {
      const valor = escaparHTML(formatMoney(obterValor(item)));
      const sinal = tipo === 'entrada' ? '+' : '−';

      return `
        <tr>
          <td>${escaparHTML(formatarData(obterData(item)))}</td>
          <td>${escaparHTML(obterDescricao(item))}</td>
          <td>${escaparHTML(obterConta(item))}</td>
          <td>${escaparHTML(obterPagamento(item))}</td>
          <td class="valor ${tipo}">
            ${sinal} ${valor}
          </td>
        </tr>
      `;
    })
    .join('');
}

function criarHTMLRelatorio({
  entradas,
  saidas,
  totalEntradas,
  totalSaidas,
  saldoTotal,
  formatMoney,
}) {
  const margemOperacional =
    totalEntradas > 0
      ? (saldoTotal / totalEntradas) * 100
      : 0;

  return `
    <!DOCTYPE html>
    <html lang="pt-BR">
      <head>
        <meta charset="UTF-8" />
        <meta
          name="viewport"
          content="width=device-width, initial-scale=1.0"
        />
        <title>Relatório Financeiro</title>

        <style>
          * {
            box-sizing: border-box;
          }

          body {
            padding: 24px;
            color: #292929;
            font-family: Arial, sans-serif;
          }

          h1 {
            margin: 0 0 20px;
            font-size: 22px;
          }

          h2 {
            margin: 26px 0 12px;
            font-size: 16px;
          }

          .cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
          }

          .card {
            padding: 16px;
            border: 1px solid #e2e2e2;
            border-radius: 10px;
            background: #f7f7f7;
          }

          .label {
            margin-bottom: 8px;
            color: #666;
            font-size: 12px;
          }

          .value {
            font-size: 18px;
            font-weight: bold;
            white-space: nowrap;
          }

          .green {
            color: #2e8b57;
          }

          .red {
            color: #d04f4f;
          }

          .black {
            color: #292929;
          }

          table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
          }

          th,
          td {
            padding: 10px 8px;
            border: 1px solid #e5e5e5;
            text-align: left;
            font-size: 10px;
            vertical-align: top;
          }

          th {
            background: #f3f3f3;
            color: #555;
          }

          .valor {
            font-weight: bold;
            white-space: nowrap;
          }

          .entrada {
            color: #2e8b57;
          }

          .saida {
            color: #d04f4f;
          }

          .empty {
            padding: 16px;
            color: #777;
            text-align: center;
          }

          @media print {
            body {
              padding: 0;
            }

            .card,
            table,
            tr {
              break-inside: avoid;
            }
          }
        </style>
      </head>

      <body>
        <h1>Relatório Consolidado</h1>

        <section class="cards">
          <div class="card">
            <div class="label">Receita Bruta</div>
            <div class="value green">
              ${escaparHTML(formatMoney(totalEntradas))}
            </div>
          </div>

          <div class="card">
            <div class="label">Custos e Despesas</div>
            <div class="value red">
              ${escaparHTML(formatMoney(totalSaidas))}
            </div>
          </div>

          <div class="card">
            <div class="label">Lucro Líquido</div>
            <div class="value ${
              saldoTotal >= 0 ? 'green' : 'red'
            }">
              ${escaparHTML(formatMoney(saldoTotal))}
            </div>
          </div>

          <div class="card">
            <div class="label">Margem Operacional</div>
            <div class="value black">
              ${margemOperacional.toFixed(1)}%
            </div>
          </div>
        </section>

        <h2>Entradas</h2>
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Descrição</th>
              <th>Conta</th>
              <th>Pagamento</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            ${criarLinhasPDF(
              entradas,
              'entrada',
              formatMoney
            )}
          </tbody>
        </table>

        <h2>Saídas</h2>
        <table>
          <thead>
            <tr>
              <th>Data</th>
              <th>Descrição</th>
              <th>Conta</th>
              <th>Pagamento</th>
              <th>Valor</th>
            </tr>
          </thead>
          <tbody>
            ${criarLinhasPDF(
              saidas,
              'saida',
              formatMoney
            )}
          </tbody>
        </table>
      </body>
    </html>
  `;
}

function SecaoMovimentacoes({
  titulo,
  lista,
  tipo,
  formatMoney,
}) {
  const ehEntrada = tipo === 'entrada';
  const cor = ehEntrada ? C.success : C.danger;
  const icone = ehEntrada
    ? 'arrow-up-right'
    : 'arrow-down-left';

  return (
    <View
      style={[
        styles.whiteContainerBox,
        {
          marginTop: 16,
          padding: 18,
        },
      ]}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          marginBottom: 16,
        }}>
        <View
          style={{
            width: 36,
            height: 36,
            marginRight: 10,
            borderRadius: 18,
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: ehEntrada
              ? C.successBg
              : C.dangerBg,
          }}>
          <Feather
            name={icone}
            size={18}
            color={cor}
          />
        </View>

        <View style={{ flex: 1 }}>
          <Text style={styles.boxTitle}>
            {titulo}
          </Text>

          <Text
            style={[
              styles.boxSub,
              {
                marginTop: 3,
                marginBottom: 0,
              },
            ]}>
            {lista.length}{' '}
            {lista.length === 1
              ? 'movimentação'
              : 'movimentações'}
          </Text>
        </View>
      </View>

      {lista.length === 0 ? (
        <Text
          style={[
            styles.boxSub,
            {
              marginTop: 4,
              marginBottom: 4,
              textAlign: 'center',
            },
          ]}>
          Nenhuma {ehEntrada ? 'entrada' : 'saída'} encontrada.
        </Text>
      ) : (
        lista.map((item, index) => {
          const chave =
            item.id ||
            item.cd_entrada ||
            item.cd_saida ||
            `${tipo}-${index}`;

          return (
            <View
              key={chave}
              style={{
                flexDirection: 'row',
                alignItems: 'center',
                paddingVertical: 14,
                borderTopWidth: index === 0 ? 1 : 0,
                borderBottomWidth: 1,
                borderColor: '#EEEEEE',
              }}>
              <View
                style={{
                  flex: 1,
                  paddingRight: 12,
                }}>
                <Text
                  style={{
                    color: '#333333',
                    fontSize: 14,
                    fontWeight: '600',
                  }}>
                  {obterDescricao(item)}
                </Text>

                <Text
                  style={{
                    marginTop: 6,
                    color: '#777777',
                    fontSize: 12,
                    lineHeight: 18,
                  }}>
                  {formatarData(obterData(item))}
                  {'  •  '}
                  {obterPagamento(item)}
                  {'\n'}
                  {obterConta(item)}
                </Text>
              </View>

              <Text
                style={{
                  color: cor,
                  fontSize: 14,
                  fontWeight: '700',
                }}>
                {ehEntrada ? '+' : '−'}{' '}
                {formatMoney(obterValor(item))}
              </Text>
            </View>
          );
        })
      )}
    </View>
  );
}

export default function Relatorio({
  totalEntradas = 0,
  totalSaidas = 0,
  saldoTotal = 0,
  formatMoney,
  entradas = [],
  saidas = [],
  movimentacoes = [],
}) {
  const [exportando, setExportando] = useState(false);

  const margemOperacional =
    totalEntradas > 0
      ? (saldoTotal / totalEntradas) * 100
      : 0;

  const listaEntradas =
    entradas.length > 0
      ? entradas
      : movimentacoes.filter(
          (mov) => mov.tipo === 'entrada'
        );

  const listaSaidas =
    saidas.length > 0
      ? saidas
      : movimentacoes.filter(
          (mov) => mov.tipo === 'saida'
        );

  async function exportarPDF() {
    try {
      setExportando(true);

      const html = criarHTMLRelatorio({
        entradas: listaEntradas,
        saidas: listaSaidas,
        totalEntradas,
        totalSaidas,
        saldoTotal,
        formatMoney,
      });

      const arquivo = await Print.printToFileAsync({
        html,
        base64: false,
      });

      const compartilhamentoDisponivel =
        await Sharing.isAvailableAsync();

      if (!compartilhamentoDisponivel) {
        Alert.alert(
          'PDF gerado',
          `O arquivo foi criado em: ${arquivo.uri}`
        );
        return;
      }

      await Sharing.shareAsync(arquivo.uri, {
        mimeType: 'application/pdf',
        dialogTitle: 'Salvar ou compartilhar relatório',
        UTI: 'com.adobe.pdf',
      });
    } catch (erro) {
      console.error('Erro ao exportar relatório:', erro);

      Alert.alert(
        'Erro',
        'Não foi possível gerar o PDF. Tente novamente.'
      );
    } finally {
      setExportando(false);
    }
  }

  return (
    <View style={{ paddingBottom: 20 }}>
      <View
        style={[
          styles.whiteContainerBox,
          { padding: 18 },
        ]}>
        <Text style={styles.boxTitle}>
          Relatório Consolidado
        </Text>

        <Text
          style={[
            styles.boxSub,
            {
              marginTop: 6,
              marginBottom: 18,
            },
          ]}>
          Visão executiva do desempenho da empresa
        </Text>

        <View style={styles.reportRow}>
          <Text style={styles.reportLabel}>
            Receita Bruta:
          </Text>

          <Text
            style={[
              styles.reportValue,
              { color: C.success },
            ]}>
            {formatMoney(totalEntradas)}
          </Text>
        </View>

        <View style={styles.reportRow}>
          <Text style={styles.reportLabel}>
            Custos e Despesas:
          </Text>

          <Text
            style={[
              styles.reportValue,
              { color: C.danger },
            ]}>
            {formatMoney(totalSaidas)}
          </Text>
        </View>

        <View style={styles.reportRow}>
          <Text style={styles.reportLabel}>
            Lucro Líquido:
          </Text>

          <Text
            style={[
              styles.reportValue,
              {
                color:
                  saldoTotal >= 0
                    ? C.success
                    : C.danger,
              },
            ]}>
            {formatMoney(saldoTotal)}
          </Text>
        </View>

        <View style={styles.reportRow}>
          <Text style={styles.reportLabel}>
            Margem Operacional:
          </Text>

          <Text style={styles.reportValue}>
            {margemOperacional.toFixed(1)}%
          </Text>
        </View>

        <TouchableOpacity
          style={[
            styles.purpleActionButton,
            {
              minHeight: 46,
              marginTop: 20,
              flexDirection: 'row',
              alignItems: 'center',
              justifyContent: 'center',
              opacity: exportando ? 0.7 : 1,
            },
          ]}
          onPress={exportarPDF}
          disabled={exportando}>
          {exportando ? (
            <ActivityIndicator
              size="small"
              color="#FFFFFF"
            />
          ) : (
            <Feather
              name="download"
              size={16}
              color="#FFFFFF"
            />
          )}

          <Text
            style={{
              marginLeft: 8,
              color: '#FFFFFF',
              fontWeight: 'bold',
            }}>
            {exportando
              ? 'Gerando PDF...'
              : 'Exportar PDF'}
          </Text>
        </TouchableOpacity>
      </View>

      <SecaoMovimentacoes
        titulo="Entradas"
        lista={listaEntradas}
        tipo="entrada"
        formatMoney={formatMoney}
      />

      <SecaoMovimentacoes
        titulo="Saídas"
        lista={listaSaidas}
        tipo="saida"
        formatMoney={formatMoney}
      />
    </View>
  );
}