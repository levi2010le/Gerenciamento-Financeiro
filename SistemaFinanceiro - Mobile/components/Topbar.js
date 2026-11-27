import React from 'react';
import { Text, View } from 'react-native';

import styles from '../styles';
import ProfileMenu from './ProfileMenu';

export default function Topbar({
  screen,
  email,
  nomeUsuario,
  onSaveName,
  onChangePassword,
  onLogout,
}) {
  return (
    <View style={styles.topbar}>
      <View style={{ flexDirection: 'row', alignItems: 'center' }}>
        <View style={styles.logoDotSmall} />
        <Text style={styles.pageTitleHeading}>
          LEKAN · {screen}
        </Text>
      </View>

      <ProfileMenu
        email={email}
        nomeUsuario={nomeUsuario}
        onSaveName={onSaveName}
        onChangePassword={onChangePassword}
        onLogout={onLogout}
      />
    </View>
  );
}