import React, { useState } from 'react';
import {
  Alert,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

import styles from '../styles';
import { C } from '../theme';

export default function Metas({
  metasLista,
  formatMoney,
  onNovaMeta,
  onDepositarMeta,
  onResgatarMeta,
  onExcluirMeta,
}) {
  const [depositos, setDepositos] = useState({});
  const [resgates, setResgates] = useState({});

  function depositar(meta) {
    const concluido = onDepositarMeta(
      meta.id,
      depositos[meta.id] || ''
    );

    if (concluido) {
      setDepositos((atuais) => ({
        ...atuais,
        [meta.id]: '',
      }));

      Alert.alert(
        'Sucesso',
        'Depósito registrado na meta.'
      );
    }
  }

  function resgatar(meta) {
    const concluido = onResgatarMeta(
      meta.id,
      resgates[meta.id] || ''
    );

    if (concluido) {
      setResgates((atuais) => ({
        ...atuais,
        [meta.id]: '',
      }));

      Alert.alert(
        'Sucesso',
        'Valor devolvido ao saldo.'
      );
    }
  }

  function confirmarExclusao(meta) {
    Alert.alert(
      'Excluir meta',
      'O valor guardado será devolvido ao saldo.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Excluir',
          style: 'destructive',
          onPress: () => {
            if (onExcluirMeta(meta.id)) {
              Alert.alert(
                'Meta excluída',
                'O valor guardado voltou ao saldo.'
              );
            }
          },
        },
      ]
    );
  }

  return (
    <View style={styles.whiteContainerBox}>
      <View style={styles.metaBanner}>
        <Text style={styles.metaBannerTitle}>
          Suas Metas Financeiras
        </Text>

        <Text style={styles.metaBannerSub}>
          Acompanhe o progresso dos seus objetivos de negócio.
        </Text>
      </View>

      <TouchableOpacity
        style={[styles.purpleActionButton, { marginTop: 15 }]}
        onPress={onNovaMeta}>
        <Feather name="target" size={16} color="#FFF" />

        <Text
          style={{
            color: '#FFF',
            fontWeight: 'bold',
            marginLeft: 5,
          }}>
          Criar Nova Meta
        </Text>
      </TouchableOpacity>

      <View style={{ marginTop: 15 }}>
        {metasLista.length === 0 ? (
          <Text style={styles.emptyText}>
            Nenhuma meta cadastrada.
          </Text>
        ) : (
          metasLista.map((meta) => {
            const objetivo = Number(meta.valorMeta) || 0;
            const atual = Number(meta.valorAtual) || 0;
            const restante = Math.max(objetivo - atual, 0);

            const percentual =
              objetivo > 0
                ? Math.min((atual / objetivo) * 100, 100)
                : 0;

            return (
              <View
                key={meta.id}
                style={styles.metaCardItem}>
                <Text style={styles.metaCardTitle}>
                  {meta.titulo}
                </Text>

                <Text style={styles.metaCardDesc}>
                  {meta.desc}
                </Text>

                <View style={styles.progressBg}>
                  <View
                    style={[
                      styles.progressFill,
                      { width: `${percentual}%` },
                    ]}
                  />
                </View>

                <View
                  style={{
                    flexDirection: 'row',
                    justifyContent: 'space-between',
                    marginTop: 4,
                  }}>
                  <Text
                    style={{
                      fontSize: 11,
                      color: C.textMuted,
                    }}>
                    Guardado: {formatMoney(atual)}
                  </Text>

                  <Text
                    style={{
                      fontSize: 11,
                      fontWeight: 'bold',
                      color: C.primary,
                    }}>
                    {percentual.toFixed(0)}%
                  </Text>

                  <Text
                    style={{
                      fontSize: 11,
                      color: C.textMuted,
                    }}>
                    Meta: {formatMoney(objetivo)}
                  </Text>
                </View>

                {restante > 0 && (
                  <View style={{ marginTop: 12 }}>
                    <TextInput
                      style={styles.textInput}
                      placeholder={`Valor para depositar (faltam ${formatMoney(restante)})`}
                      keyboardType="decimal-pad"
                      value={depositos[meta.id] || ''}
                      onChangeText={(valor) =>
                        setDepositos((atuais) => ({
                          ...atuais,
                          [meta.id]: valor,
                        }))
                      }
                    />

                    <TouchableOpacity
                      style={[
                        styles.purpleActionButton,
                        { marginTop: 6 },
                      ]}
                      onPress={() => depositar(meta)}>
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
                        Depositar na meta
                      </Text>
                    </TouchableOpacity>
                  </View>
                )}

                {atual > 0 && (
                  <View style={{ marginTop: 10 }}>
                    <TextInput
                      style={styles.textInput}
                      placeholder={`Valor para resgatar (até ${formatMoney(atual)})`}
                      keyboardType="decimal-pad"
                      value={resgates[meta.id] || ''}
                      onChangeText={(valor) =>
                        setResgates((atuais) => ({
                          ...atuais,
                          [meta.id]: valor,
                        }))
                      }
                    />

                    <TouchableOpacity
                      style={[
                        styles.purpleActionButton,
                        {
                          marginTop: 6,
                          backgroundColor: '#777',
                        },
                      ]}
                      onPress={() => resgatar(meta)}>
                      <Feather
                        name="corner-up-left"
                        size={16}
                        color="#FFF"
                      />

                      <Text
                        style={{
                          color: '#FFF',
                          fontWeight: 'bold',
                          marginLeft: 5,
                        }}>
                        Resgatar para o saldo
                      </Text>
                    </TouchableOpacity>
                  </View>
                )}

                <TouchableOpacity
                  style={[
                    styles.purpleActionButton,
                    {
                      marginTop: 10,
                      backgroundColor: C.danger,
                    },
                  ]}
                  onPress={() => confirmarExclusao(meta)}>
                  <Feather
                    name="trash-2"
                    size={16}
                    color="#FFF"
                  />

                  <Text
                    style={{
                      color: '#FFF',
                      fontWeight: 'bold',
                      marginLeft: 5,
                    }}>
                    Excluir meta
                  </Text>
                </TouchableOpacity>
              </View>
            );
          })
        )}
      </View>
    </View>
  );
}