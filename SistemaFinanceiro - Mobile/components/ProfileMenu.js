import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { Feather } from '@expo/vector-icons';

import { C } from '../theme';

export default function ProfileMenu({
  email = '',
  nomeUsuario = '',
  onSaveName,
  onChangePassword,
  onLogout,
}) {
  const [visivel, setVisivel] = useState(false);
  const [nome, setNome] = useState(nomeUsuario);
  const [senhaAtual, setSenhaAtual] = useState('');
  const [novaSenha, setNovaSenha] = useState('');
  const [confirmarNovaSenha, setConfirmarNovaSenha] = useState('');
  const [acaoEmAndamento, setAcaoEmAndamento] = useState('');

  useEffect(() => {
    if (visivel) {
      setNome(nomeUsuario || '');
    }
  }, [visivel, nomeUsuario]);

  async function salvarNome() {
    const nomeLimpo = nome.trim();

    if (!nomeLimpo) {
      Alert.alert('Nome inválido', 'Informe um nome de usuário.');
      return;
    }

    if (nomeLimpo.length > 100) {
      Alert.alert(
        'Nome muito longo',
        'O nome pode ter no máximo 100 caracteres.'
      );
      return;
    }

    try {
      setAcaoEmAndamento('nome');

      const salvo = await onSaveName(nomeLimpo);

      if (salvo) {
        setNome(nomeLimpo);
        Alert.alert('Sucesso', 'Nome de usuário atualizado.');
      }
    } catch (erro) {
      Alert.alert('Erro', 'Não foi possível salvar o nome.');
    } finally {
      setAcaoEmAndamento('');
    }
  }

  async function alterarSenha() {
    if (!senhaAtual || !novaSenha || !confirmarNovaSenha) {
      Alert.alert('Atenção', 'Preencha todos os campos de senha.');
      return;
    }

    if (novaSenha.length < 6) {
      Alert.alert(
        'Senha inválida',
        'A nova senha deve ter pelo menos 6 caracteres.'
      );
      return;
    }

    if (novaSenha !== confirmarNovaSenha) {
      Alert.alert(
        'Senhas diferentes',
        'A nova senha e a confirmação não coincidem.'
      );
      return;
    }

    if (senhaAtual === novaSenha) {
      Alert.alert(
        'Senha não alterada',
        'A nova senha deve ser diferente da senha atual.'
      );
      return;
    }

    try {
      setAcaoEmAndamento('senha');

      const alterada = await onChangePassword(
        senhaAtual,
        novaSenha,
        confirmarNovaSenha
      );

      if (alterada) {
        setSenhaAtual('');
        setNovaSenha('');
        setConfirmarNovaSenha('');
        Alert.alert('Sucesso', 'Senha alterada com sucesso.');
      }
    } catch (erro) {
      Alert.alert('Erro', 'Não foi possível alterar a senha.');
    } finally {
      setAcaoEmAndamento('');
    }
  }

  function sair() {
    setVisivel(false);
    onLogout();
  }

  function campoSenha({
    label,
    valor,
    onChangeText,
    placeholder,
    autoComplete,
  }) {
    return (
      <View style={{ marginBottom: 12 }}>
        <Text
          style={{
            marginBottom: 6,
            color: '#555',
            fontSize: 12,
            fontWeight: '700',
          }}>
          {label}
        </Text>

        <TextInput
          value={valor}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor="#999"
          secureTextEntry
          autoCapitalize="none"
          autoComplete={autoComplete}
          style={{
            height: 42,
            paddingHorizontal: 12,
            borderWidth: 1,
            borderColor: '#DCDCDC',
            borderRadius: 8,
            color: '#222',
            fontSize: 14,
          }}
        />
      </View>
    );
  }

  return (
    <>
      <TouchableOpacity
        accessibilityRole="button"
        accessibilityLabel="Abrir informações do usuário"
        activeOpacity={0.75}
        onPress={() => setVisivel(true)}
        style={{
          width: 40,
          height: 40,
          borderRadius: 20,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: '#F3F0FF',
        }}>
        <Feather name="user" size={20} color={C.primary} />
      </TouchableOpacity>

      <Modal
        visible={visivel}
        transparent
        animationType="fade"
        onRequestClose={() => setVisivel(false)}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={{
            flex: 1,
            backgroundColor: 'rgba(0, 0, 0, 0.35)',
          }}>
          <Pressable
            onPress={() => setVisivel(false)}
            style={{
              flex: 1,
              alignItems: 'flex-end',
              paddingTop: 70,
              paddingHorizontal: 16,
            }}>
            <Pressable
              onPress={() => {}}
              style={{
                width: '100%',
                maxWidth: 360,
                maxHeight: '85%',
                borderRadius: 14,
                backgroundColor: '#FFFFFF',
                elevation: 8,
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 4 },
                shadowOpacity: 0.18,
                shadowRadius: 8,
                overflow: 'hidden',
              }}>
              <ScrollView
                keyboardShouldPersistTaps="handled"
                showsVerticalScrollIndicator
                style={{ flexShrink: 1 }}
                contentContainerStyle={{
                  padding: 20,
                  paddingBottom: 36,
                }}>
                <Text
                  style={{
                    marginBottom: 18,
                    color: '#222',
                    fontSize: 18,
                    fontWeight: '700',
                  }}>
                  Meu perfil
                </Text>

                <Text
                  style={{
                    marginBottom: 6,
                    color: '#555',
                    fontSize: 12,
                    fontWeight: '700',
                  }}>
                  E-mail
                </Text>

                <View
                  style={{
                    minHeight: 42,
                    justifyContent: 'center',
                    marginBottom: 16,
                    paddingHorizontal: 12,
                    borderWidth: 1,
                    borderColor: '#E5E5E5',
                    borderRadius: 8,
                    backgroundColor: '#F8F8F8',
                  }}>
                  <Text
                    selectable
                    style={{
                      color: '#444',
                      fontSize: 14,
                    }}>
                    {email || 'E-mail não informado'}
                  </Text>
                </View>

                <Text
                  style={{
                    marginBottom: 6,
                    color: '#555',
                    fontSize: 12,
                    fontWeight: '700',
                  }}>
                  Nome de usuário
                </Text>

                <TextInput
                  value={nome}
                  onChangeText={setNome}
                  placeholder="Digite seu nome"
                  placeholderTextColor="#999"
                  maxLength={100}
                  style={{
                    height: 42,
                    marginBottom: 10,
                    paddingHorizontal: 12,
                    borderWidth: 1,
                    borderColor: '#DCDCDC',
                    borderRadius: 8,
                    color: '#222',
                    fontSize: 14,
                  }}
                />

                <TouchableOpacity
                  activeOpacity={0.8}
                  disabled={Boolean(acaoEmAndamento)}
                  onPress={salvarNome}
                  style={{
                    minHeight: 42,
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 18,
                    borderRadius: 8,
                    backgroundColor: C.primary,
                    opacity: acaoEmAndamento ? 0.7 : 1,
                  }}>
                  {acaoEmAndamento === 'nome' ? (
                    <ActivityIndicator
                      size="small"
                      color="#FFFFFF"
                    />
                  ) : (
                    <Text
                      style={{
                        color: '#FFFFFF',
                        fontWeight: '700',
                      }}>
                      Salvar nome
                    </Text>
                  )}
                </TouchableOpacity>

                <View
                  style={{
                    height: 1,
                    marginBottom: 16,
                    backgroundColor: '#EEEEEE',
                  }}
                />

                <Text
                  style={{
                    marginBottom: 6,
                    color: '#222',
                    fontSize: 15,
                    fontWeight: '700',
                  }}>
                  Alterar senha
                </Text>

                <Text
                  style={{
                    marginBottom: 14,
                    color: '#777',
                    fontSize: 12,
                    lineHeight: 18,
                  }}>
                  A senha cadastrada não é exibida. Informe sua senha
                  atual para definir uma nova.
                </Text>

                {campoSenha({
                  label: 'Senha atual',
                  valor: senhaAtual,
                  onChangeText: setSenhaAtual,
                  placeholder: 'Digite sua senha atual',
                  autoComplete: 'current-password',
                })}

                {campoSenha({
                  label: 'Nova senha',
                  valor: novaSenha,
                  onChangeText: setNovaSenha,
                  placeholder: 'Mínimo de 6 caracteres',
                  autoComplete: 'new-password',
                })}

                {campoSenha({
                  label: 'Confirmar nova senha',
                  valor: confirmarNovaSenha,
                  onChangeText: setConfirmarNovaSenha,
                  placeholder: 'Digite novamente a nova senha',
                  autoComplete: 'new-password',
                })}

                <TouchableOpacity
                  activeOpacity={0.8}
                  disabled={Boolean(acaoEmAndamento)}
                  onPress={alterarSenha}
                  style={{
                    minHeight: 42,
                    flexDirection: 'row',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 16,
                    borderRadius: 8,
                    backgroundColor: C.primary,
                    opacity: acaoEmAndamento ? 0.7 : 1,
                  }}>
                  {acaoEmAndamento === 'senha' ? (
                    <ActivityIndicator
                      size="small"
                      color="#FFFFFF"
                    />
                  ) : (
                    <Text
                      style={{
                        color: '#FFFFFF',
                        fontWeight: '700',
                      }}>
                      Alterar senha
                    </Text>
                  )}
                </TouchableOpacity>

                <TouchableOpacity
                  activeOpacity={0.8}
                  onPress={sair}
                  style={{
                    minHeight: 42,
                    flexDirection: 'row',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 4,
                    borderRadius: 8,
                    backgroundColor: C.danger,
                  }}>
                  <Feather
                    name="log-out"
                    size={16}
                    color="#FFFFFF"
                  />

                  <Text
                    style={{
                      marginLeft: 8,
                      color: '#FFFFFF',
                      fontSize: 14,
                      fontWeight: '700',
                    }}>
                    Sair
                  </Text>
                </TouchableOpacity>
              </ScrollView>
            </Pressable>
          </Pressable>
        </KeyboardAvoidingView>
      </Modal>
    </>
  );
}