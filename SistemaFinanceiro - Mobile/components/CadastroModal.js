import React from 'react';
import {
  Modal,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

import styles from '../styles';
import { C } from '../theme';

export default function CadastroModal({
  tipo,
  visible,
  onClose,
  onSave,
  novaDescricao,
  setNovaDescricao,
  novoValor,
  setNovoValor,
  novaContaOrg,
  setNovaContaOrg,
  novoVencimento,
  setNovoVencimento,
  tipoMov,
  setTipoMov,
  formaPagamento,
  setFormaPagamento,
}) {
  const titulos = {
    conta: 'Nova Conta a Pagar',
    movimentacao: 'Lançar Movimentação',
    meta: 'Criar Nova Meta',
  };

  const formasPagamento = [
    { id: 'pix', titulo: 'Pix' },
    { id: 'CARTAO_CREDITO', titulo: 'Crédito' },
    { id: 'CARTAO_DEBITO', titulo: 'Débito' },
    { id: 'dinheiro', titulo: 'Dinheiro' },
  ];

  const handleValorChange = (texto) => {
    const valorLimpo = texto
      .replace(/[^\d.,]/g, '')
      .replace(',', '.');

    const partes = valorLimpo.split('.');
    const inteiro = partes[0];
    const decimal = partes.slice(1).join('');

    setNovoValor(
      valorLimpo.includes('.')
        ? `${inteiro}.${decimal}`
        : inteiro
    );
  };

  const handleVencimentoChange = (texto) => {
    const digitos = texto
      .replace(/\D/g, '')
      .slice(0, 8);

    let data = digitos;

    if (digitos.length > 4) {
      data =
        `${digitos.slice(0, 2)}/` +
        `${digitos.slice(2, 4)}/` +
        `${digitos.slice(4)}`;
    } else if (digitos.length > 2) {
      data =
        `${digitos.slice(0, 2)}/` +
        `${digitos.slice(2)}`;
    }

    setNovoVencimento(data);
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <View style={styles.modalContent}>
          <Text style={styles.modalTitle}>
            {titulos[tipo]}
          </Text>

          {tipo === 'movimentacao' && (
            <>
              <Text style={styles.sectionTitle}>
                Tipo de movimentação
              </Text>

              <View
                style={{
                  flexDirection: 'row',
                  marginBottom: 12,
                }}>
                <TouchableOpacity
                  activeOpacity={0.7}
                  style={[
                    styles.typeBtn,
                    tipoMov === 'entrada' && {
                      backgroundColor: C.successBg,
                      borderColor: C.success,
                    },
                  ]}
                  onPress={() => setTipoMov('entrada')}>
                  <Text
                    style={{
                      color:
                        tipoMov === 'entrada'
                          ? C.success
                          : C.textMuted,
                      fontWeight: 'bold',
                    }}>
                    Entrada
                  </Text>
                </TouchableOpacity>

                <TouchableOpacity
                  activeOpacity={0.7}
                  style={[
                    styles.typeBtn,
                    tipoMov === 'saida' && {
                      backgroundColor: C.dangerBg,
                      borderColor: C.danger,
                    },
                  ]}
                  onPress={() => setTipoMov('saida')}>
                  <Text
                    style={{
                      color:
                        tipoMov === 'saida'
                          ? C.danger
                          : C.textMuted,
                      fontWeight: 'bold',
                    }}>
                    Saída
                  </Text>
                </TouchableOpacity>
              </View>

              <Text style={styles.sectionTitle}>
                Forma de pagamento
              </Text>

              <View
                style={{
                  flexDirection: 'row',
                  flexWrap: 'wrap',
                  marginBottom: 12,
                }}>
                {formasPagamento.map((forma) => {
                  const selecionada =
                    formaPagamento === forma.id;

                  return (
                    <TouchableOpacity
                      key={forma.id}
                      activeOpacity={0.7}
                      style={[
                        styles.typeBtn,
                        {
                          flexBasis: '46%',
                          flexGrow: 1,
                          marginBottom: 6,
                        },
                        selecionada && {
                          backgroundColor: C.successBg,
                          borderColor: C.success,
                        },
                      ]}
                      onPress={() =>
                        setFormaPagamento(forma.id)
                      }>
                      <Text
                        style={{
                          color: selecionada
                            ? C.success
                            : C.textMuted,
                          fontWeight: 'bold',
                          textAlign: 'center',
                        }}>
                        {forma.titulo}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </>
          )}

          <TextInput
            style={styles.textInput}
            placeholder={
              tipo === 'conta'
                ? 'Descrição (ex: Água)'
                : tipo === 'meta'
                  ? 'Título da Meta'
                  : 'Descrição'
            }
            value={novaDescricao}
            onChangeText={setNovaDescricao}
          />

          {tipo === 'meta' && (
            <TextInput
              style={styles.textInput}
              placeholder="Descrição breve"
              value={novaContaOrg}
              onChangeText={setNovaContaOrg}
            />
          )}

          <TextInput
            style={styles.textInput}
            placeholder={
              tipo === 'meta'
                ? 'Valor Alvo (ex: 10000)'
                : tipo === 'conta'
                  ? 'Valor (ex: 150.00)'
                  : 'Valor (ex: 250.00)'
            }
            keyboardType="decimal-pad"
            value={novoValor}
            onChangeText={handleValorChange}
          />

          {tipo === 'conta' && (
            <>
              <TextInput
                style={styles.textInput}
                placeholder="Empresa / Fornecedor"
                value={novaContaOrg}
                onChangeText={setNovaContaOrg}
              />

              <TextInput
                style={styles.textInput}
                placeholder="Vencimento (DD/MM/AAAA)"
                keyboardType="number-pad"
                maxLength={10}
                value={novoVencimento}
                onChangeText={handleVencimentoChange}
              />
            </>
          )}

          <View
            style={{
              flexDirection: 'row',
              justifyContent: 'space-between',
              marginTop: 10,
            }}>
            <TouchableOpacity
              style={styles.modalBtnCancel}
              onPress={onClose}>
              <Text
                style={{
                  color: C.textMuted,
                  fontWeight: 'bold',
                }}>
                Cancelar
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.modalBtnSave}
              onPress={onSave}>
              <Text
                style={{
                  color: '#FFF',
                  fontWeight: 'bold',
                }}>
                Salvar
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}