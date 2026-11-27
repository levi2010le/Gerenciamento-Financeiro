import React, { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TextInput,
  TouchableOpacity,
  View,
  Alert,
} from 'react-native';

import styles from '../styles';
import { C } from '../theme';

export default function Login({
  screen,
  setScreen,
  email,
  setEmail,
  password,
  setPassword,
  confirmPassword,
  setConfirmPassword,
  onLogin,
  onRegister,
}) {
  const isCadastro = screen === 'Cadastrar';
  const [localEmail, setLocalEmail] = useState(email || '');

 
  const validarEmail = (emailParaTestar) => {
    const regexEmailCom = /^[^\s@]+@[^\s@]+\.com$/i;
    return regexEmailCom.test(emailParaTestar);
  };

  const handleSubmit = () => {
    const emailLimpo = localEmail.trim().toLowerCase();

    if (!validarEmail(emailLimpo)) {
      Alert.alert(
        'E-mail inválido',
        'Por favor, insira um e-mail válido que termine obrigatoriamente com .com (ex: usuario@dominio.com).'
      );
      return;
    }

    
    setEmail(emailLimpo);

    
    if (isCadastro) {
      onRegister();
    } else {
      onLogin();
    }
  };

  return (
    <SafeAreaView style={styles.authContainer}>
      <StatusBar barStyle="light-content" backgroundColor={C.primary} />

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={styles.authScroll}>
          <View style={styles.authHeaderBox}>
            <View style={styles.logoContainer}>
              <View style={styles.logoDot} />
              <Text style={styles.logoText}>LEKAN</Text>
            </View>

            <Text style={styles.logoSubtext}>
              Gestão financeira empresarial mobile
            </Text>
          </View>

          <View style={styles.authCardBox}>
            <Text style={styles.authTitle}>
              {isCadastro ? 'Criar Conta' : 'Entrar'}
            </Text>

            <Text style={styles.inputLabel}>E-mail *</Text>
            <TextInput
              style={styles.textInput}
              placeholder="seu@email.com"
              placeholderTextColor="#A0AEC0"
              value={localEmail}
              onChangeText={setLocalEmail}
              autoCapitalize="none"
              keyboardType="email-address"
            />

            <Text style={styles.inputLabel}>Senha *</Text>
            <TextInput
              style={styles.textInput}
              placeholder="Sua senha"
              placeholderTextColor="#A0AEC0"
              secureTextEntry
              value={password}
              onChangeText={setPassword}
            />

            {isCadastro && (
              <>
                <Text style={styles.inputLabel}>Confirmar senha *</Text>
                <TextInput
                  style={styles.textInput}
                  placeholder="Confirme sua senha"
                  placeholderTextColor="#A0AEC0"
                  secureTextEntry
                  value={confirmPassword}
                  onChangeText={setConfirmPassword}
                />
              </>
            )}

            <TouchableOpacity
              style={styles.primaryButton}
              onPress={handleSubmit}>
              <Text style={styles.primaryButtonText}>Avançar</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={{ marginTop: 20, alignItems: 'center' }}
              onPress={() => setScreen(isCadastro ? 'Login' : 'Cadastrar')}>
              <Text style={{ color: C.primary, fontWeight: '600' }}>
                {isCadastro
                  ? 'Já possui conta? Entrar'
                  : 'Não tem uma conta? Cadastre-se'}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}