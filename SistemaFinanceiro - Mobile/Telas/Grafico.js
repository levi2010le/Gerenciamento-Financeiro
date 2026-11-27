import React from 'react';
import { Text, View } from 'react-native';
import Svg, { Circle, Path } from 'react-native-svg';

import styles from '../styles';
import { C } from '../theme';

function criarFatia(cx, cy, raio, anguloInicial, anguloFinal) {
  const radianos = (angulo) => (angulo * Math.PI) / 180;

  const x1 = cx + raio * Math.cos(radianos(anguloInicial));
  const y1 = cy + raio * Math.sin(radianos(anguloInicial));
  const x2 = cx + raio * Math.cos(radianos(anguloFinal));
  const y2 = cy + raio * Math.sin(radianos(anguloFinal));

  const arcoGrande =
    anguloFinal - anguloInicial > 180 ? 1 : 0;

  return [
    `M ${cx} ${cy}`,
    `L ${x1} ${y1}`,
    `A ${raio} ${raio} 0 ${arcoGrande} 1 ${x2} ${y2}`,
    'Z',
  ].join(' ');
}

export default function Grafico({
  movimentacoes = [],
  totalEntradas = 0,
  totalSaidas = 0,
  formatMoney,
}) {
  const entradas = Math.max(Number(totalEntradas) || 0, 0);
  const saidas = Math.max(Number(totalSaidas) || 0, 0);
  const total = entradas + saidas;

  const anguloEntradas =
    total > 0 ? (entradas / total) * 360 : 0;

  const maiorValor = Math.max(entradas, saidas, 1);

  const percentualSaidas =
    entradas > 0 ? (saidas / entradas) * 100 : 0;

  const despesasPorDescricao = {};

  movimentacoes
    .filter((mov) => mov.tipo === 'saida')
    .forEach((mov) => {
      const descricao =
        String(mov.desc || '').trim() || 'Saída sem descrição';

      despesasPorDescricao[descricao] =
        (despesasPorDescricao[descricao] || 0) +
        (Number(mov.valor) || 0);
    });

  const despesas = Object.entries(despesasPorDescricao)
    .map(([descricao, valor]) => ({ descricao, valor }))
    .sort((a, b) => b.valor - a.valor);

  const maiorDespesa =
    despesas.length > 0 ? despesas[0].valor : 1;

  function dinheiro(valor) {
    return typeof formatMoney === 'function'
      ? formatMoney(valor)
      : `R$ ${Number(valor || 0).toFixed(2)}`;
  }

  return (
    <View style={styles.whiteContainerBox}>
      <Text style={styles.boxTitle}>
        Distribuição Financeira
      </Text>

      {total > 0 ? (
        <View
          style={{
            alignItems: 'center',
            marginVertical: 15,
          }}>
          <Svg width={200} height={200} viewBox="0 0 120 120">
            {entradas > 0 && saidas > 0 ? (
              <>
                <Path
                  d={criarFatia(
                    60,
                    60,
                    55,
                    -90,
                    -90 + anguloEntradas
                  )}
                  fill={C.success}
                />

                <Path
                  d={criarFatia(
                    60,
                    60,
                    55,
                    -90 + anguloEntradas,
                    270
                  )}
                  fill={C.danger}
                />
              </>
            ) : (
              <Circle
                cx={60}
                cy={60}
                r={55}
                fill={entradas > 0 ? C.success : C.danger}
              />
            )}
          </Svg>

          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'center',
              marginTop: 10,
            }}>
            <Text
              style={{
                fontSize: 12,
                color: C.success,
                marginRight: 18,
              }}>
              ● Entradas
            </Text>

            <Text
              style={{
                fontSize: 12,
                color: C.danger,
              }}>
              ● Saídas
            </Text>
          </View>
        </View>
      ) : (
        <Text
          style={{
            color: C.textMuted,
            textAlign: 'center',
            marginVertical: 15,
          }}>
          Sem valores para exibir no gráfico.
        </Text>
      )}

      <Text
        style={[
          styles.boxTitle,
          { marginTop: 8, marginBottom: 10 },
        ]}>
        Saídas por descrição
      </Text>

      {despesas.length === 0 ? (
        <Text
          style={{
            color: C.textMuted,
            textAlign: 'center',
            marginBottom: 14,
          }}>
          Nenhuma saída registrada.
        </Text>
      ) : (
        <View style={{ marginBottom: 18 }}>
          {despesas.map((despesa) => (
            <View
              key={despesa.descricao}
              style={{ marginBottom: 11 }}>
              <View
                style={{
                  flexDirection: 'row',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  marginBottom: 4,
                }}>
                <Text
                  numberOfLines={1}
                  style={{
                    flex: 1,
                    marginRight: 8,
                    fontSize: 12,
                    color: C.textDark,
                  }}>
                  {despesa.descricao}
                </Text>

                <Text
                  style={{
                    fontSize: 12,
                    fontWeight: 'bold',
                    color: C.danger,
                  }}>
                  {dinheiro(despesa.valor)}
                </Text>
              </View>

              <View
                style={{
                  height: 8,
                  borderRadius: 4,
                  overflow: 'hidden',
                  backgroundColor: '#E8E8E8',
                }}>
                <View
                  style={{
                    width: `${(despesa.valor / maiorDespesa) * 100}%`,
                    height: '100%',
                    borderRadius: 4,
                    backgroundColor: C.danger,
                  }}
                />
              </View>
            </View>
          ))}
        </View>
      )}

      <View
        style={{
          height: 1,
          backgroundColor: C.border,
          marginBottom: 16,
        }}
      />

      <Text style={styles.boxTitle}>
        Comparativo de Entradas e Saídas
      </Text>

      <View style={{ marginTop: 12 }}>
        <View style={{ marginBottom: 12 }}>
          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginBottom: 5,
            }}>
            <Text
              style={{
                fontSize: 12,
                color: C.textDark,
              }}>
              Entradas
            </Text>

            <Text
              style={{
                fontSize: 12,
                fontWeight: 'bold',
                color: C.success,
              }}>
              {dinheiro(entradas)}
            </Text>
          </View>

          <View
            style={{
              height: 10,
              borderRadius: 5,
              overflow: 'hidden',
              backgroundColor: '#E8E8E8',
            }}>
            <View
              style={{
                width: `${(entradas / maiorValor) * 100}%`,
                height: '100%',
                backgroundColor: C.success,
              }}
            />
          </View>
        </View>

        <View>
          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginBottom: 5,
            }}>
            <Text
              style={{
                fontSize: 12,
                color: C.textDark,
              }}>
              Saídas
            </Text>

            <Text
              style={{
                fontSize: 12,
                fontWeight: 'bold',
                color: C.danger,
              }}>
              {dinheiro(saidas)}
            </Text>
          </View>

          <View
            style={{
              height: 10,
              borderRadius: 5,
              overflow: 'hidden',
              backgroundColor: '#E8E8E8',
            }}>
            <View
              style={{
                width: `${(saidas / maiorValor) * 100}%`,
                height: '100%',
                backgroundColor: C.danger,
              }}
            />
          </View>
        </View>

        <Text
          style={{
            color: C.textMuted,
            fontSize: 11,
            marginTop: 10,
            textAlign: 'right',
          }}>
          Saídas equivalem a {percentualSaidas.toFixed(1)}% das entradas
        </Text>
      </View>
    </View>
  );
}