import React from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import { Feather } from '@expo/vector-icons';

import styles from '../styles';
import { C } from '../theme';

const itens = [
  { name: 'Geral', icon: 'grid' },
  { name: 'Pendências', icon: 'credit-card' }, // Corrigido o acento/grafia
  { name: 'Gráfico', icon: 'pie-chart' },
  { name: 'Saldo', icon: 'dollar-sign' },
  { name: 'Metas', icon: 'target' },
  { name: 'Relatório', icon: 'file-text' },
];

export default function BottomNav({ screen, onNavigate }) {
  return (
    <View style={styles.bottomNav}>
      {itens.map((item) => {
        // Considera ativo se o estado atual for 'Pendências' ou 'Contas'
        const isActive = 
          screen === item.name || 
          (item.name === 'Pendências' && screen === 'Contas');

        return (
          <TouchableOpacity
            key={item.name}
            style={styles.bottomNavItem}
            onPress={() => {
             
              if (item.name === 'Pendências') {
                onNavigate('Contas');
              } else {
                onNavigate(item.name);
              }
            }}>
            <Feather
              name={item.icon}
              size={18}
              color={isActive ? C.primary : C.textMuted}
            />
            <Text
              style={[
                styles.bottomNavText,
                isActive && styles.bottomNavTextActive,
              ]}>
              {item.name}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}