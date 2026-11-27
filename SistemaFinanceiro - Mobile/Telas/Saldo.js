import React from 'react';
import {
  Alert,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

import styles from '../styles';
import { C } from '../theme';

export default function Saldo({
  movimentacoes,
  saldoTotal,
  formatMoney,
  onNovaMovimentacao,
  onEditarMovimentacao,
  onExcluirMovimentacao,
}) {
  function confirmarExclusao(movimentacao) {
    Alert.alert(
      'Excluir movimentação',
      `Deseja excluir "${movimentacao.desc}"?`,
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Excluir',
          style: 'destructive',
          onPress: () =>
            onExcluirMovimentacao(movimentacao),
        },
      ]
    );
  }

  return (
    <View style={styles.whiteContainerBox}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}>
        <Text style={styles.boxTitle}>
          Detalhamento do Saldo
        </Text>

        <Feather
          name="credit-card"
          size={20}
          color={C.primary}
        />
      </View>

      <View
        style={{
          marginVertical: 14,
          padding: 16,
          borderRadius: 12,
          backgroundColor: C.primaryLight || '#F3F0FF',
        }}>
        <Text
          style={{
            color: C.textMuted,
            marginBottom: 4,
          }}>
          Saldo disponível
        </Text>

        <Text
          style={{
            fontSize: 24,
            fontWeight: 'bold',
            color: C.primary,
          }}>
          {formatMoney(saldoTotal)}
        </Text>
      </View>

      <TouchableOpacity
        activeOpacity={0.8}
        style={[
          styles.purpleActionButton,
          { justifyContent: 'center' },
        ]}
        onPress={onNovaMovimentacao}>
        <Feather
          name="plus-circle"
          size={16}
          color="#FFF"
        />

        <Text
          style={{
            color: '#FFF',
            fontWeight: 'bold',
            marginLeft: 5,
          }}>
          Lançar entrada ou saída
        </Text>
      </TouchableOpacity>

      <View
        style={{
          height: 1,
          backgroundColor: '#EEE',
          marginVertical: 20,
        }}
      />

      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: 10,
        }}>
        <Text style={styles.boxTitle}>
          Histórico do Extrato
        </Text>

        {movimentacoes.length > 0 && (
          <Text
            style={{
              color: C.textMuted,
              fontSize: 12,
            }}>
            {movimentacoes.length}{' '}
            {movimentacoes.length === 1
              ? 'lançamento'
              : 'lançamentos'}
          </Text>
        )}
      </View>

      {movimentacoes.length === 0 ? (
        <View
          style={{
            alignItems: 'center',
            paddingVertical: 24,
            borderRadius: 10,
            backgroundColor: '#FAFAFA',
          }}>
          <Feather
            name="file-text"
            size={28}
            color={C.textMuted}
          />

          <Text
            style={[
              styles.emptyText,
              { marginTop: 8 },
            ]}>
            Nenhum lançamento encontrado
          </Text>

          <Text
            style={{
              color: C.textMuted,
              fontSize: 12,
              marginTop: 4,
            }}>
            Seus lançamentos aparecerão aqui
          </Text>
        </View>
      ) : (
        movimentacoes.map((movimentacao) => (
          <View
            key={movimentacao.id}
            style={[
              styles.mobileCardItem,
              {
                flexDirection: 'row',
                alignItems: 'center',
                marginBottom: 8,
              },
            ]}>
            <View
              style={{
                width: 38,
                height: 38,
                borderRadius: 19,
                marginRight: 10,
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor:
                  movimentacao.tipo === 'entrada'
                    ? `${C.success}20`
                    : `${C.danger}20`,
              }}>
              <Feather
                name={
                  movimentacao.tipo === 'entrada'
                    ? 'arrow-down-left'
                    : 'arrow-up-right'
                }
                size={18}
                color={
                  movimentacao.tipo === 'entrada'
                    ? C.success
                    : C.danger
                }
              />
            </View>

            <View style={{ flex: 1 }}>
              <View
                style={{
                  flexDirection: 'row',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}>
                <Text
                  style={{
                    flex: 1,
                    fontWeight: 'bold',
                    fontSize: 14,
                    color: C.textDark,
                    marginRight: 8,
                  }}
                  numberOfLines={1}>
                  {movimentacao.desc}
                </Text>

                <Text
                  style={{
                    fontWeight: 'bold',
                    color:
                      movimentacao.tipo === 'entrada'
                        ? C.success
                        : C.danger,
                  }}>
                  {movimentacao.tipo === 'entrada'
                    ? '+'
                    : '-'}{' '}
                  {formatMoney(movimentacao.valor)}
                </Text>
              </View>

              <Text
                style={{
                  fontSize: 11,
                  color: C.textMuted,
                  marginTop: 4,
                }}>
                {movimentacao.data}
                {movimentacao.formaPagamento
                  ? ` • ${movimentacao.formaPagamento}`
                  : ''}
              </Text>

              <View
                style={{
                  flexDirection: 'row',
                  justifyContent: 'flex-end',
                  marginTop: 8,
                }}>
                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={() =>
                    onEditarMovimentacao(movimentacao)
                  }
                  style={{
                    width: 34,
                    height: 34,
                    borderRadius: 17,
                    marginRight: 8,
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: '#F1F1F1',
                  }}>
                  <Feather
                    name="edit-2"
                    size={15}
                    color={C.primary}
                  />
                </TouchableOpacity>

                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={() =>
                    confirmarExclusao(movimentacao)
                  }
                  style={{
                    width: 34,
                    height: 34,
                    borderRadius: 17,
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: '#FFF0F0',
                  }}>
                  <Feather
                    name="trash-2"
                    size={15}
                    color={C.danger}
                  />
                </TouchableOpacity>
              </View>
            </View>
          </View>
        ))
      )}
    </View>
  );
}