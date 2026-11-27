import React from 'react';
import { Alert, Text, TouchableOpacity, View } from 'react-native';
import { Feather } from '@expo/vector-icons';

import styles from '../styles';
import { C } from '../theme';

export default function Contas({
  contasAPagar = [],
  totalEmAberto = 0,
  totalPago = 0,
  saldoTotal = 0,
  formatMoney,
  onNovaConta,
  onPagarConta,
  onOcultarConta,
}) {
  function confirmarPagamento(conta) {
    const valor = Number(conta.valor) || 0;

    if (valor <= 0) {
      Alert.alert('Erro', 'O valor da conta é inválido.');
      return;
    }

    if (valor > saldoTotal) {
      Alert.alert(
        'Saldo insuficiente',
        'Não há saldo suficiente para pagar esta conta.'
      );
      return;
    }

    Alert.alert(
      'Pagar conta',
      `Deseja pagar "${conta.descricao}" no valor de ${formatMoney(valor)}?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Pagar',
          onPress: () => onPagarConta(conta.id),
        },
      ]
    );
  }

  function confirmarOcultar(conta) {
    Alert.alert(
      'Ocultar conta',
      `Deseja ocultar "${conta.descricao}" da lista?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Ocultar',
          onPress: () => onOcultarConta(conta.id),
        },
      ]
    );
  }

  const contasVisiveis = contasAPagar.filter(
    (conta) => !conta.oculta
  );

  return (
    <View style={styles.whiteContainerBox}>
      <View
        style={{
          flexDirection: 'row',
          justifyContent: 'space-between',
          marginBottom: 15,
        }}>
        <View style={styles.summaryBadgeBox}>
          <Text style={styles.summaryBadgeLabel}>Em Aberto</Text>
          <Text style={styles.summaryBadgeVal}>
            {formatMoney(totalEmAberto)}
          </Text>
        </View>

        <View style={styles.summaryBadgeBox}>
          <Text style={styles.summaryBadgeLabel}>Pago no Mês</Text>
          <Text
            style={[
              styles.summaryBadgeVal,
              { color: C.success },
            ]}>
            {formatMoney(totalPago)}
          </Text>
        </View>
      </View>

      <Text
        style={{
          color: C.textMuted,
          fontSize: 12,
          marginBottom: 10,
        }}>
        Saldo disponível: {formatMoney(saldoTotal)}
      </Text>

      <TouchableOpacity
        style={styles.purpleActionButton}
        onPress={onNovaConta}>
        <Feather name="plus" size={16} color="#FFF" />

        <Text
          style={{
            color: '#FFF',
            fontWeight: 'bold',
            marginLeft: 5,
          }}>
          Nova Conta
        </Text>
      </TouchableOpacity>

      <View style={{ marginTop: 15 }}>
        {contasVisiveis.length === 0 ? (
          <Text style={styles.emptyText}>
            Nenhuma conta cadastrada.
          </Text>
        ) : (
          contasVisiveis.map((conta) => {
            const paga = Boolean(conta.pago);
            const valor = Number(conta.valor) || 0;
            const saldoInsuficiente = valor > saldoTotal;

            return (
              <View
                key={conta.id}
                style={[
                  styles.mobileCardItem,
                  {
                    borderLeftColor: paga
                      ? C.success
                      : C.danger,
                    borderLeftWidth: 4,
                    marginBottom: 8,
                    backgroundColor: paga
                      ? '#F8F9FA'
                      : '#FFF',
                  },
                ]}>
                <View
                  style={{
                    flexDirection: 'row',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                  }}>
                  <View
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      flex: 1,
                    }}>
                    <Feather
                      name={paga ? 'check-circle' : 'clock'}
                      size={18}
                      color={paga ? C.success : C.danger}
                      style={{ marginRight: 8 }}
                    />

                    <Text
                      style={{
                        flex: 1,
                        fontWeight: 'bold',
                        fontSize: 14,
                        color: C.textDark,
                        textDecorationLine: paga
                          ? 'line-through'
                          : 'none',
                      }}
                      numberOfLines={1}>
                      {conta.descricao}
                    </Text>
                  </View>

                  <Text
                    style={{
                      color: paga ? C.success : C.danger,
                      fontWeight: 'bold',
                      fontSize: 14,
                      marginLeft: 10,
                    }}>
                    {formatMoney(valor)}
                  </Text>
                </View>

                <View
                  style={{
                    flexDirection: 'row',
                    justifyContent: 'space-between',
                    marginTop: 6,
                  }}>
                  <Text
                    style={{
                      fontSize: 12,
                      color: C.textMuted,
                    }}>
                    Conta: {conta.conta || 'Geral'}
                  </Text>

                  <Text
                    style={{
                      fontSize: 12,
                      color: C.textMuted,
                    }}>
                    Venc.: {conta.vencimento || '—'}
                  </Text>
                </View>

                {paga ? (
                  <View
                    style={{
                      flexDirection: 'row',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      marginTop: 10,
                    }}>
                    <Text
                      style={{
                        color: C.success,
                        fontSize: 11,
                        fontWeight: 'bold',
                      }}>
                      Paga — descontada do saldo
                    </Text>

                    <TouchableOpacity
                      activeOpacity={0.7}
                      onPress={() =>
                        confirmarOcultar(conta)
                      }
                      style={{
                        flexDirection: 'row',
                        alignItems: 'center',
                        paddingVertical: 4,
                        paddingHorizontal: 6,
                      }}>
                      <Feather
                        name="eye-off"
                        size={13}
                        color={C.textMuted}
                      />

                      <Text
                        style={{
                          fontSize: 11,
                          color: C.textMuted,
                          marginLeft: 4,
                        }}>
                        Ocultar
                      </Text>
                    </TouchableOpacity>
                  </View>
                ) : (
                  <TouchableOpacity
                    style={[
                      styles.purpleActionButton,
                      {
                        marginTop: 10,
                        backgroundColor: saldoInsuficiente
                          ? '#999'
                          : C.primary,
                      },
                    ]}
                    disabled={saldoInsuficiente}
                    onPress={() =>
                      confirmarPagamento(conta)
                    }>
                    <Feather
                      name="check"
                      size={16}
                      color="#FFF"
                    />

                    <Text
                      style={{
                        color: '#FFF',
                        fontWeight: 'bold',
                        marginLeft: 5,
                      }}>
                      Pagar
                    </Text>
                  </TouchableOpacity>
                )}
              </View>
            );
          })
        )}
      </View>
    </View>
  );
}