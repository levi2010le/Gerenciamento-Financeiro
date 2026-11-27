import React, { useState } from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import { Feather } from '@expo/vector-icons';

import styles from '../styles';
import { C } from '../theme';

function normalizarTexto(texto) {
  return String(texto || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizarPagamento(valor) {
  const metodo = normalizarTexto(valor);

  if (metodo === 'pix') return 'pix';
  if (metodo === 'dinheiro') return 'dinheiro';

  if (metodo.includes('credito')) {
    return 'cartao_credito';
  }

  if (metodo.includes('debito')) {
    return 'cartao_debito';
  }

  // Compatibilidade com movimentações antigas salvas como "cartao".
  if (metodo === 'cartao') {
    return 'cartao';
  }

  return metodo;
}

export default function Geral({
  movimentacoes = [],
  saldoTotal = 0,
  formatMoney,
  onNovaMovimentacao,
  onNavigate,
}) {
  const [filtroPagamento, setFiltroPagamento] =
    useState('todos');

  const opcoesFiltro = [
    { valor: 'todos', titulo: 'Todos' },
    { valor: 'pix', titulo: 'Pix' },
    { valor: 'cartao_credito', titulo: 'Crédito' },
    { valor: 'cartao_debito', titulo: 'Débito' },
    { valor: 'dinheiro', titulo: 'Dinheiro' },
  ];

  const movimentacoesFiltradas = movimentacoes.filter((mov) => {
    if (filtroPagamento === 'todos') {
      return true;
    }

    const metodo = normalizarPagamento(
      mov.formaPagamento || mov.metodo
    );

    return metodo === filtroPagamento;
  });

  const totalEntradas = movimentacoes
    .filter((mov) => mov.tipo === 'entrada')
    .reduce(
      (total, mov) => total + (Number(mov.valor) || 0),
      0
    );

  const totalSaidas = movimentacoes
    .filter((mov) => mov.tipo === 'saida')
    .reduce(
      (total, mov) => total + (Number(mov.valor) || 0),
      0
    );

  const somaTotal = totalEntradas + totalSaidas;

  const percEntradas =
    somaTotal > 0
      ? (totalEntradas / somaTotal) * 100
      : 0;

  const percSaidas =
    somaTotal > 0
      ? (totalSaidas / somaTotal) * 100
      : 0;

  function formatarFormaPagamento(movimentacao) {
    const metodo = normalizarPagamento(
      movimentacao.formaPagamento ||
      movimentacao.metodo
    );

    const formatos = {
      pix: 'Pix',
      cartao_credito: 'Cartão de crédito',
      cartao_debito: 'Cartão de débito',
      cartao: 'Cartão',
      dinheiro: 'Dinheiro',
    };

    return formatos[metodo] || '';
  }

  const tituloFiltroAtivo =
    opcoesFiltro.find(
      (opcao) => opcao.valor === filtroPagamento
    )?.titulo || '';

  return (
    <>
      <View
        style={[
          styles.sectionBoxLarge,
          {
            backgroundColor: C.primaryDark,
          },
        ]}>
        <Text
          style={[
            styles.boxTitle,
            { color: '#FFFFFF' },
          ]}>
          Resumo Financeiro
        </Text>

        <View
          style={{
            marginTop: 12,
            padding: 16,
            borderRadius: 12,
            backgroundColor: '#FFFFFF',
            borderLeftWidth: 5,
            borderLeftColor: C.primary,
          }}>
          <Text
            style={[
              styles.kpiLabel,
              { color: '#777777' },
            ]}>
            Saldo Total
          </Text>

          <Text
            style={[
              styles.kpiValue,
              {
                color: '#3B82F6',
                fontSize: 28,
              },
            ]}>
            {formatMoney(saldoTotal)}
          </Text>
        </View>

        <View
          style={{
            flexDirection: 'row',
            gap: 10,
            marginTop: 10,
          }}>
          <View
            style={{
              flex: 1,
              padding: 12,
              borderRadius: 12,
              backgroundColor: C.successBg,
            }}>
            <Text style={styles.kpiLabel}>
              Entradas
            </Text>

            <Text
              style={[
                styles.kpiValue,
                { color: C.success, fontSize: 18 },
              ]}
              numberOfLines={1}
              adjustsFontSizeToFit>
              {formatMoney(totalEntradas)}
            </Text>
          </View>

          <View
            style={{
              flex: 1,
              padding: 12,
              borderRadius: 12,
              backgroundColor: C.dangerBg,
            }}>
            <Text style={styles.kpiLabel}>
              Saídas
            </Text>

            <Text
              style={[
                styles.kpiValue,
                { color: C.danger, fontSize: 18 },
              ]}
              numberOfLines={1}
              adjustsFontSizeToFit>
              {formatMoney(totalSaidas)}
            </Text>
          </View>
        </View>
      </View>

      <View style={styles.sectionBoxLarge}>
        <Text style={styles.boxTitle}>
          Ações Rápidas
        </Text>

        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            marginTop: 10,
          }}>
          <TouchableOpacity
            style={[
              styles.quickActionButton,
              {
                justifyContent: 'space-between',
                paddingHorizontal: 14,
              },
            ]}
            onPress={onNovaMovimentacao}>
            <View
              style={{
                flexDirection: 'row',
                alignItems: 'center',
              }}>
              <Feather
                name="plus-circle"
                size={18}
                color="#FFF"
              />

              <Text
                style={[
                  styles.quickActionText,
                  { marginLeft: 6 },
                ]}>
                Movimentação
              </Text>
            </View>

            <Feather
              name="chevron-right"
              size={18}
              color="#FFF"
            />
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.quickActionButton,
              { backgroundColor: C.primaryDark },
            ]}
            onPress={() => onNavigate('Relatório')}>
            <Feather
              name="file-text"
              size={18}
              color="#FFF"
            />

            <Text style={styles.quickActionText}>
              Relatórios
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.sectionBoxSmall}>
        <Text style={styles.boxTitle}>
          Últimas Movimentações
        </Text>

        <View
          style={{
            flexDirection: 'row',
            flexWrap: 'wrap',
            marginTop: 10,
            marginBottom: 12,
          }}>
          {opcoesFiltro.map((opcao) => {
            const ativo = filtroPagamento === opcao.valor;

            return (
              <TouchableOpacity
                key={opcao.valor}
                onPress={() => setFiltroPagamento(opcao.valor)}
                style={{
                  flexGrow: 1,
                  flexBasis: '18%',
                  marginHorizontal: 2,
                  marginBottom: 6,
                  paddingVertical: 7,
                  paddingHorizontal: 4,
                  borderRadius: 16,
                  alignItems: 'center',
                  backgroundColor: ativo
                    ? C.primary
                    : '#EAEAEA',
                }}>
                <Text
                  numberOfLines={1}
                  style={{
                    fontSize: 11,
                    fontWeight: '600',
                    color: ativo ? '#FFF' : '#555',
                  }}>
                  {opcao.titulo}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        {movimentacoesFiltradas.length === 0 ? (
          <Text style={styles.emptyText}>
            Nenhuma movimentação encontrada
            {filtroPagamento !== 'todos'
              ? ` para ${tituloFiltroAtivo.toLowerCase()}`
              : ''}
            .
          </Text>
        ) : (
          movimentacoesFiltradas
            .slice(0, 4)
            .map((mov) => {
              const formaPagamento =
                formatarFormaPagamento(mov);

              return (
                <View
                  key={mov.id}
                  style={styles.movRow}>
                  <View
                    style={[
                      styles.movIcon,
                      {
                        backgroundColor:
                          mov.tipo === 'entrada'
                            ? C.successBg
                            : C.dangerBg,
                      },
                    ]}>
                    <Feather
                      name={
                        mov.tipo === 'entrada'
                          ? 'arrow-up-right'
                          : 'arrow-down-left'
                      }
                      size={14}
                      color={
                        mov.tipo === 'entrada'
                          ? C.success
                          : C.danger
                      }
                    />
                  </View>

                  <View
                    style={{
                      flex: 1,
                      marginLeft: 10,
                    }}>
                    <Text style={styles.movText}>
                      {mov.desc}
                    </Text>

                    <Text style={styles.movVal}>
                      {mov.data}
                      {formaPagamento
                        ? ` • ${formaPagamento}`
                        : ''}
                    </Text>
                  </View>

                  <Text
                    style={{
                      fontWeight: 'bold',
                      color:
                        mov.tipo === 'entrada'
                          ? C.success
                          : C.danger,
                    }}>
                    {mov.tipo === 'entrada' ? '+' : '-'}{' '}
                    {formatMoney(mov.valor)}
                  </Text>
                </View>
              );
            })
        )}
      </View>

      <View style={styles.sectionBoxSmall}>
        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: 12,
          }}>
          <Text style={styles.boxTitle}>
            Resumo do Fluxo
          </Text>

          <TouchableOpacity
            onPress={() => onNavigate('Gráfico')}>
            <Text
              style={{
                fontSize: 12,
                color: C.primary,
                fontWeight: '600',
              }}>
              Ver detalhes
            </Text>
          </TouchableOpacity>
        </View>

        <View style={{ marginBottom: 12 }}>
          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginBottom: 4,
            }}>
            <Text
              style={{
                fontSize: 12,
                color: '#555',
                fontWeight: '600',
              }}>
              Entradas ({percEntradas.toFixed(0)}%)
            </Text>

            <Text
              style={{
                fontSize: 12,
                color: C.success,
                fontWeight: 'bold',
              }}>
              {formatMoney(totalEntradas)}
            </Text>
          </View>

          <View
            style={{
              height: 10,
              backgroundColor: '#EAEAEA',
              borderRadius: 5,
              overflow: 'hidden',
            }}>
            <View
              style={{
                width: `${percEntradas}%`,
                height: '100%',
                backgroundColor: C.success,
                borderRadius: 5,
              }}
            />
          </View>
        </View>

        <View>
          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginBottom: 4,
            }}>
            <Text
              style={{
                fontSize: 12,
                color: '#555',
                fontWeight: '600',
              }}>
              Saídas ({percSaidas.toFixed(0)}%)
            </Text>

            <Text
              style={{
                fontSize: 12,
                color: C.danger,
                fontWeight: 'bold',
              }}>
              {formatMoney(totalSaidas)}
            </Text>
          </View>

          <View
            style={{
              height: 10,
              backgroundColor: '#EAEAEA',
              borderRadius: 5,
              overflow: 'hidden',
            }}>
            <View
              style={{
                width: `${percSaidas}%`,
                height: '100%',
                backgroundColor: C.danger,
                borderRadius: 5,
              }}
            />
          </View>
        </View>
      </View>
    </>
  );
}