import React, { useEffect, useState } from 'react';
import {
  Alert,
  SafeAreaView,
  ScrollView,
  StatusBar,
} from 'react-native';

import { initDatabase, openDatabase } from './database';
import { C } from './theme';
import styles from './styles';

import BottomNav from './components/BottomNav';
import CadastroModal from './components/CadastroModal';
import Topbar from './components/Topbar';

import Login from './Telas/Login';
import Geral from './Telas/Geral';
import Contas from './Telas/Contas';
import Grafico from './Telas/Grafico';
import Saldo from './Telas/Saldo';
import Metas from './Telas/Metas';
import Relatorio from './Telas/Relatorio';

const CHAVE_DADOS_PROJETO = 'dados_projeto';

export default function App() {
  const [databaseReady, setDatabaseReady] = useState(false);
  const [screen, setScreen] = useState('Login');

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  // Guarda o registro do usuário atualmente conectado: { id, email, nome }.
  const [usuarioLogado, setUsuarioLogado] = useState(null);

  const [movimentacoes, setMovimentacoes] = useState([]);
  const [contasAPagar, setContasAPagar] = useState([]);
  const [metasLista, setMetasLista] = useState([]);

  // Só permite salvar depois que os dados daquele usuário forem carregados.
  const [dadosCarregadosDoUsuarioId, setDadosCarregadosDoUsuarioId] =
    useState(null);

  const [modalContaVisible, setModalContaVisible] = useState(false);
  const [modalMovVisible, setModalMovVisible] = useState(false);
  const [modalMetaVisible, setModalMetaVisible] = useState(false);
  const [modalEditarMovVisible, setModalEditarMovVisible] =
    useState(false);

  const [movimentacaoEditando, setMovimentacaoEditando] =
    useState(null);

  const [novaDescricao, setNovaDescricao] = useState('');
  const [novoValor, setNovoValor] = useState('');
  const [novaContaOrg, setNovaContaOrg] = useState('');
  const [novoVencimento, setNovoVencimento] = useState('');
  const [tipoMov, setTipoMov] = useState('entrada');
  const [formaPagamento, setFormaPagamento] = useState('pix');

  useEffect(() => {
    async function prepararBanco() {
      try {
        const db = await initDatabase();

        // Dados das telas são persistidos no SQLite por usuário.
        await db.execAsync(`
          CREATE TABLE IF NOT EXISTS app_state (
            usuario_id INTEGER PRIMARY KEY,
            dados TEXT NOT NULL,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id)
              REFERENCES usuarios(id) ON DELETE CASCADE
          );
        `);

        setDatabaseReady(true);
      } catch (erro) {
        console.error('Erro ao iniciar o SQLite:', erro);
        Alert.alert(
          'Erro',
          'Não foi possível iniciar o banco de dados local.'
        );
      }
    }

    prepararBanco();
  }, []);

  useEffect(() => {
    const usuarioId = usuarioLogado?.id;

    if (
      !databaseReady ||
      !usuarioId ||
      dadosCarregadosDoUsuarioId !== usuarioId
    ) {
      return;
    }

    async function salvarDadosDoUsuario() {
      try {
        const db = await openDatabase();
        const dados = JSON.stringify({
          movimentacoes,
          contasAPagar,
          metasLista,
        });

        await db.runAsync(
          `INSERT INTO app_state (usuario_id, dados, atualizado_em)
           VALUES (?, ?, CURRENT_TIMESTAMP)
           ON CONFLICT(usuario_id) DO UPDATE SET
             dados = excluded.dados,
             atualizado_em = CURRENT_TIMESTAMP`,
          usuarioId,
          dados
        );
      } catch (erro) {
        console.error('Erro ao salvar dados no SQLite:', erro);
      }
    }

    salvarDadosDoUsuario();
  }, [
    databaseReady,
    usuarioLogado,
    dadosCarregadosDoUsuarioId,
    movimentacoes,
    contasAPagar,
    metasLista,
  ]);

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

  const saldoTotal = totalEntradas - totalSaidas;

  const totalEmAberto = contasAPagar
    .filter((conta) => !conta.pago)
    .reduce(
      (total, conta) => total + (Number(conta.valor) || 0),
      0
    );

  const totalPago = contasAPagar
    .filter((conta) => conta.pago)
    .reduce(
      (total, conta) => total + (Number(conta.valor) || 0),
      0
    );

  const formatMoney = (valor) =>
    Number(valor || 0).toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    });

  async function handleLogin() {
    if (!databaseReady) {
      Alert.alert('Aguarde', 'O banco de dados ainda está iniciando.');
      return;
    }

    const emailLimpo = email.trim().toLowerCase();

    if (!emailLimpo || !password) {
      Alert.alert('Atenção', 'Preencha o e-mail e a senha.');
      return;
    }

    try {
      const db = await openDatabase();

      const usuario = await db.getFirstAsync(
        `SELECT id, nome, email, senha
         FROM usuarios
         WHERE lower(email) = lower(?)
         LIMIT 1`,
        emailLimpo
      );

      if (!usuario) {
        Alert.alert(
          'Conta não encontrada',
          'Este e-mail não possui uma conta neste dispositivo.'
        );
        return;
      }

      if (usuario.senha !== password) {
        Alert.alert('Senha incorreta', 'A senha digitada está errada.');
        return;
      }

      setDadosCarregadosDoUsuarioId(null);

      const estadoSalvo = await db.getFirstAsync(
        `SELECT dados
         FROM app_state
         WHERE usuario_id = ?`,
        usuario.id
      );

      let dados = {
        movimentacoes: [],
        contasAPagar: [],
        metasLista: [],
      };

      if (estadoSalvo?.dados) {
        try {
          const dadosParseados = JSON.parse(estadoSalvo.dados);

          dados = {
            movimentacoes: Array.isArray(dadosParseados.movimentacoes)
              ? dadosParseados.movimentacoes
              : [],
            contasAPagar: Array.isArray(dadosParseados.contasAPagar)
              ? dadosParseados.contasAPagar
              : [],
            metasLista: Array.isArray(dadosParseados.metasLista)
              ? dadosParseados.metasLista
              : [],
          };
        } catch (erroParse) {
          console.error('Erro ao ler dados salvos:', erroParse);
        }
      }

      setMovimentacoes(dados.movimentacoes);
      setContasAPagar(dados.contasAPagar);
      setMetasLista(dados.metasLista);

      setUsuarioLogado({
        id: usuario.id,
        email: usuario.email,
        nome: usuario.nome || '',
      });

      setDadosCarregadosDoUsuarioId(usuario.id);
      setPassword('');
      setConfirmPassword('');
      setScreen('Geral');
    } catch (erro) {
      console.error('Erro ao fazer login:', erro);
      Alert.alert('Erro', 'Não foi possível entrar na conta.');
    }
  }

  async function handleRegister() {
    if (!databaseReady) {
      Alert.alert('Aguarde', 'O banco de dados ainda está iniciando.');
      return;
    }

    const emailLimpo = email.trim().toLowerCase();

    if (!emailLimpo || !password || !confirmPassword) {
      Alert.alert('Atenção', 'Preencha todos os campos.');
      return;
    }

    if (password !== confirmPassword) {
      Alert.alert('Erro', 'As senhas não coincidem.');
      return;
    }

    if (password.length < 6) {
      Alert.alert(
        'Senha inválida',
        'A senha deve ter pelo menos 6 caracteres.'
      );
      return;
    }

    try {
      const db = await openDatabase();

      const usuarioExistente = await db.getFirstAsync(
        `SELECT id FROM usuarios WHERE lower(email) = lower(?) LIMIT 1`,
        emailLimpo
      );

      if (usuarioExistente) {
        Alert.alert('Atenção', 'Este e-mail já está cadastrado.');
        setScreen('Login');
        return;
      }

      const nomeInicial = emailLimpo.split('@')[0] || 'Usuário';

      const resultado = await db.runAsync(
        `INSERT INTO usuarios (nome, email, senha)
         VALUES (?, ?, ?)`,
        nomeInicial,
        emailLimpo,
        password
      );

      const usuarioId = resultado.lastInsertRowId;

      // Cria um banco padrão, necessário para o schema financeiro.
      await db.runAsync(
        `INSERT INTO bancos (usuario_id, nome, saldo, moeda)
         VALUES (?, ?, ?, ?)`,
        usuarioId,
        'Geral',
        0,
        'BRL'
      );

      Alert.alert('Sucesso', 'Conta cadastrada com sucesso!');
      setPassword('');
      setConfirmPassword('');
      setEmail(emailLimpo);
      setScreen('Login');
    } catch (erro) {
      console.error('Erro ao cadastrar usuário:', erro);
      Alert.alert('Erro', 'Não foi possível cadastrar a conta.');
    }
  }

  async function handleSaveName(nomeNovo) {
    if (!usuarioLogado?.id) {
      Alert.alert('Erro', 'Nenhum usuário está conectado.');
      return false;
    }

    const nomeLimpo = String(nomeNovo || '').trim();

    if (!nomeLimpo || nomeLimpo.length > 100) {
      Alert.alert(
        'Nome inválido',
        'Informe um nome com no máximo 100 caracteres.'
      );
      return false;
    }

    try {
      const db = await openDatabase();

      const resultado = await db.runAsync(
        `UPDATE usuarios SET nome = ? WHERE id = ?`,
        nomeLimpo,
        usuarioLogado.id
      );

      if (!resultado.changes) {
        Alert.alert('Erro', 'Não foi possível encontrar o usuário.');
        return false;
      }

      setUsuarioLogado((atual) => ({
        ...atual,
        nome: nomeLimpo,
      }));

      return true;
    } catch (erro) {
      console.error('Erro ao salvar nome:', erro);
      Alert.alert('Erro', 'Não foi possível salvar o nome.');
      return false;
    }
  }

  async function handleChangePassword(
    senhaAtual,
    novaSenha,
    confirmarNovaSenha
  ) {
    if (!usuarioLogado?.id) {
      Alert.alert('Erro', 'Nenhum usuário está conectado.');
      return false;
    }

    if (novaSenha.length < 6) {
      Alert.alert(
        'Senha inválida',
        'A nova senha deve ter pelo menos 6 caracteres.'
      );
      return false;
    }

    if (novaSenha !== confirmarNovaSenha) {
      Alert.alert(
        'Senhas diferentes',
        'A nova senha e a confirmação não coincidem.'
      );
      return false;
    }

    if (senhaAtual === novaSenha) {
      Alert.alert(
        'Senha não alterada',
        'A nova senha deve ser diferente da senha atual.'
      );
      return false;
    }

    try {
      const db = await openDatabase();

      // A senha só é atualizada se a senha atual corresponder.
      const resultado = await db.runAsync(
        `UPDATE usuarios
         SET senha = ?
         WHERE id = ? AND senha = ?`,
        novaSenha,
        usuarioLogado.id,
        senhaAtual
      );

      if (!resultado.changes) {
        Alert.alert('Senha incorreta', 'A senha atual está incorreta.');
        return false;
      }

      return true;
    } catch (erro) {
      console.error('Erro ao alterar senha:', erro);
      Alert.alert('Erro', 'Não foi possível alterar a senha.');
      return false;
    }
  }

  function handleLogout() {
    setDadosCarregadosDoUsuarioId(null);
    setUsuarioLogado(null);

    setMovimentacoes([]);
    setContasAPagar([]);
    setMetasLista([]);

    setEmail('');
    setPassword('');
    setConfirmPassword('');

    setNovaDescricao('');
    setNovoValor('');
    setNovaContaOrg('');
    setNovoVencimento('');

    setTipoMov('entrada');
    setFormaPagamento('pix');

    setMovimentacaoEditando(null);
    setScreen('Login');
  }

  function limparForm() {
    setNovaDescricao('');
    setNovoValor('');
    setNovaContaOrg('');
    setNovoVencimento('');
  }

  function handleAddConta() {
    if (!novaDescricao.trim() || !novoValor || !novoVencimento) {
      Alert.alert(
        'Erro',
        'Preencha a descrição, o valor e o vencimento da conta.'
      );
      return;
    }

    const valor = parseFloat(novoValor.replace(',', '.')) || 0;

    if (valor <= 0) {
      Alert.alert('Erro', 'Informe um valor maior que zero.');
      return;
    }

    setContasAPagar((contas) => [
      ...contas,
      {
        id: String(Date.now()),
        descricao: novaDescricao.trim(),
        valor,
        vencimento: novoVencimento,
        conta: novaContaOrg.trim() || 'Geral',
        pago: false,
        oculta: false,
      },
    ]);

    setModalContaVisible(false);
    limparForm();
  }

  function handlePagarConta(id) {
    const conta = contasAPagar.find((item) => item.id === id);

    if (!conta || conta.pago) return;

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

    setContasAPagar((contas) =>
      contas.map((item) =>
        item.id === id ? { ...item, pago: true } : item
      )
    );

    setMovimentacoes((atuais) => {
      if (atuais.some((mov) => mov.contaId === id)) {
        return atuais;
      }

      return [
        {
          id: `pagamento-conta-${id}`,
          contaId: id,
          tipo: 'saida',
          desc: `Pagamento: ${conta.descricao}`,
          valor,
          data: new Date().toLocaleDateString('pt-BR'),
          formaPagamento: conta.formaPagamento || 'dinheiro',
        },
        ...atuais,
      ];
    });
  }

  function handleOcultarConta(contaId) {
    setContasAPagar((contas) =>
      contas.map((conta) =>
        conta.id === contaId ? { ...conta, oculta: true } : conta
      )
    );
  }

  function handleAddMovimentacao() {
    if (!novaDescricao.trim() || !novoValor) {
      Alert.alert('Erro', 'Preencha a descrição e o valor.');
      return;
    }

    const valor = parseFloat(novoValor.replace(',', '.')) || 0;

    if (valor <= 0) {
      Alert.alert('Erro', 'Informe um valor maior que zero.');
      return;
    }

    if (tipoMov === 'saida' && valor > saldoTotal) {
      Alert.alert(
        'Saldo insuficiente',
        'A saída não pode ser maior que o saldo disponível.'
      );
      return;
    }

    setMovimentacoes((atuais) => [
      {
        id: String(Date.now()),
        tipo: tipoMov,
        desc: novaDescricao.trim(),
        valor,
        data: new Date().toLocaleDateString('pt-BR'),
        formaPagamento,
      },
      ...atuais,
    ]);

    setModalMovVisible(false);
    limparForm();
  }

  function handleAddMeta() {
    if (!novaDescricao.trim() || !novoValor) {
      Alert.alert('Erro', 'Preencha o título e o valor da meta.');
      return;
    }

    const valorMeta = parseFloat(novoValor.replace(',', '.')) || 0;

    if (valorMeta <= 0) {
      Alert.alert('Erro', 'Informe um valor maior que zero.');
      return;
    }

    setMetasLista((metas) => [
      ...metas,
      {
        id: String(Date.now()),
        titulo: novaDescricao.trim(),
        desc: novaContaOrg.trim() || 'Meta de longo prazo',
        valorMeta,
        valorAtual: 0,
      },
    ]);

    setModalMetaVisible(false);
    limparForm();
  }

  function converterValorMeta(valorInformado) {
    const texto = String(valorInformado ?? '')
      .trim()
      .replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$/.test(texto)) {
      return null;
    }

    const valor = Number(texto);

    if (!Number.isFinite(valor)) {
      return null;
    }

    return Math.round(valor * 100) / 100;
  }

  function handleDepositarMeta(metaId, valorInformado) {
    const meta = metasLista.find((item) => item.id === metaId);
    const valor = converterValorMeta(valorInformado);

    if (!meta) {
      Alert.alert('Erro', 'Meta não encontrada.');
      return false;
    }

    if (valor === null || valor <= 0) {
      Alert.alert('Valor inválido', 'Informe um valor maior que zero.');
      return false;
    }

    const atual = Number(meta.valorAtual) || 0;
    const objetivo = Number(meta.valorMeta) || 0;
    const restante = Math.max(objetivo - atual, 0);

    if (valor > restante) {
      Alert.alert(
        'Valor acima do necessário',
        `Você pode depositar no máximo ${formatMoney(restante)}.`
      );
      return false;
    }

    if (valor > saldoTotal) {
      Alert.alert(
        'Saldo insuficiente',
        `Seu saldo disponível é ${formatMoney(saldoTotal)}.`
      );
      return false;
    }

    setMetasLista((metas) =>
      metas.map((item) =>
        item.id === metaId
          ? {
              ...item,
              valorAtual:
                Math.round(
                  ((Number(item.valorAtual) || 0) + valor) * 100
                ) / 100,
            }
          : item
      )
    );

    setMovimentacoes((atuais) => [
      {
        id: `meta-deposito-${metaId}-${Date.now()}`,
        metaId,
        metaOperacao: 'deposito',
        tipo: 'saida',
        desc: `Depósito na meta: ${meta.titulo}`,
        valor,
        data: new Date().toLocaleDateString('pt-BR'),
        formaPagamento: 'dinheiro',
      },
      ...atuais,
    ]);

    return true;
  }

  function handleResgatarMeta(metaId, valorInformado) {
    const meta = metasLista.find((item) => item.id === metaId);
    const valor = converterValorMeta(valorInformado);

    if (!meta) {
      Alert.alert('Erro', 'Meta não encontrada.');
      return false;
    }

    if (valor === null || valor <= 0) {
      Alert.alert('Valor inválido', 'Informe um valor maior que zero.');
      return false;
    }

    const atual = Number(meta.valorAtual) || 0;

    if (valor > atual) {
      Alert.alert(
        'Valor acima do guardado',
        `Você pode resgatar no máximo ${formatMoney(atual)}.`
      );
      return false;
    }

    setMetasLista((metas) =>
      metas.map((item) =>
        item.id === metaId
          ? {
              ...item,
              valorAtual:
                Math.round(
                  ((Number(item.valorAtual) || 0) - valor) * 100
                ) / 100,
            }
          : item
      )
    );

    setMovimentacoes((atuais) => [
      {
        id: `meta-resgate-${metaId}-${Date.now()}`,
        metaId,
        metaOperacao: 'resgate',
        tipo: 'entrada',
        desc: `Resgate da meta: ${meta.titulo}`,
        valor,
        data: new Date().toLocaleDateString('pt-BR'),
        formaPagamento: 'dinheiro',
      },
      ...atuais,
    ]);

    return true;
  }

  function handleExcluirMeta(metaId) {
    const meta = metasLista.find((item) => item.id === metaId);

    if (!meta) {
      Alert.alert('Erro', 'Meta não encontrada.');
      return false;
    }

    const valorGuardado = Number(meta.valorAtual) || 0;

    setMetasLista((metas) =>
      metas.filter((item) => item.id !== metaId)
    );

    if (valorGuardado > 0) {
      setMovimentacoes((atuais) => [
        {
          id: `meta-exclusao-${metaId}-${Date.now()}`,
          tipo: 'entrada',
          desc: `Devolução da meta excluída: ${meta.titulo}`,
          valor: valorGuardado,
          data: new Date().toLocaleDateString('pt-BR'),
          formaPagamento: 'dinheiro',
        },
        ...atuais,
      ]);
    }

    return true;
  }

  function abrirEdicaoMovimentacao(movimentacao) {
    setMovimentacaoEditando(movimentacao);
    setNovaDescricao(movimentacao.desc);
    setNovoValor(String(movimentacao.valor));
    setTipoMov(movimentacao.tipo);
    setFormaPagamento(movimentacao.formaPagamento || 'dinheiro');
    setModalEditarMovVisible(true);
  }

  function handleEditarMovimentacao() {
    if (!movimentacaoEditando) return;

    if (!novaDescricao.trim() || !novoValor) {
      Alert.alert('Erro', 'Preencha a descrição e o valor.');
      return;
    }

    const valorNovo = parseFloat(novoValor.replace(',', '.')) || 0;

    if (valorNovo <= 0) {
      Alert.alert('Erro', 'Informe um valor maior que zero.');
      return;
    }

    const valorAntigo = Number(movimentacaoEditando.valor) || 0;

    if (tipoMov === 'saida') {
      const saldoSemMovimentacao =
        movimentacaoEditando.tipo === 'entrada'
          ? saldoTotal - valorAntigo
          : saldoTotal + valorAntigo;

      if (valorNovo > saldoSemMovimentacao) {
        Alert.alert(
          'Saldo insuficiente',
          'A saída não pode ser maior que o saldo disponível.'
        );
        return;
      }
    }

    setMovimentacoes((atuais) =>
      atuais.map((mov) =>
        mov.id === movimentacaoEditando.id
          ? {
              ...mov,
              tipo: tipoMov,
              desc: novaDescricao.trim(),
              valor: valorNovo,
              formaPagamento,
            }
          : mov
      )
    );

    if (
      movimentacaoEditando.metaId &&
      movimentacaoEditando.metaOperacao
    ) {
      setMetasLista((atuais) =>
        atuais.map((meta) => {
          if (meta.id !== movimentacaoEditando.metaId) {
            return meta;
          }

          const atual = Number(meta.valorAtual) || 0;
          let novoAtual = atual;

          if (movimentacaoEditando.metaOperacao === 'deposito') {
            novoAtual = atual - valorAntigo + valorNovo;
          } else {
            novoAtual = atual + valorAntigo - valorNovo;
          }

          const objetivo = Number(meta.valorMeta) || 0;

          return {
            ...meta,
            valorAtual:
              Math.round(
                Math.max(0, Math.min(novoAtual, objetivo)) * 100
              ) / 100,
          };
        })
      );
    }

    if (movimentacaoEditando.contaId) {
      setContasAPagar((atuais) =>
        atuais.map((conta) =>
          conta.id === movimentacaoEditando.contaId
            ? {
                ...conta,
                descricao: novaDescricao
                  .trim()
                  .replace(/^Pagamento:\s*/, ''),
                valor: valorNovo,
              }
            : conta
        )
      );
    }

    setModalEditarMovVisible(false);
    setMovimentacaoEditando(null);
    limparForm();
  }

  function excluirMovimentacao(movimentacao) {
    Alert.alert(
      'Excluir movimentação',
      'Deseja excluir esta movimentação?',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Excluir',
          style: 'destructive',
          onPress: () => {
            const valor = Number(movimentacao.valor) || 0;

            if (movimentacao.metaId && movimentacao.metaOperacao) {
              setMetasLista((atuais) =>
                atuais.map((meta) => {
                  if (meta.id !== movimentacao.metaId) {
                    return meta;
                  }

                  const atual = Number(meta.valorAtual) || 0;
                  const novoAtual =
                    movimentacao.metaOperacao === 'deposito'
                      ? atual - valor
                      : atual + valor;

                  return {
                    ...meta,
                    valorAtual: Math.max(
                      0,
                      Math.round(novoAtual * 100) / 100
                    ),
                  };
                })
              );
            }

            if (movimentacao.contaId) {
              setContasAPagar((atuais) =>
                atuais.map((conta) =>
                  conta.id === movimentacao.contaId
                    ? { ...conta, pago: false }
                    : conta
                )
              );
            }

            setMovimentacoes((atuais) =>
              atuais.filter((mov) => mov.id !== movimentacao.id)
            );
          },
        },
      ]
    );
  }

  if (screen === 'Login' || screen === 'Cadastrar') {
    return (
      <Login
        screen={screen}
        setScreen={setScreen}
        email={email}
        setEmail={setEmail}
        password={password}
        setPassword={setPassword}
        confirmPassword={confirmPassword}
        setConfirmPassword={setConfirmPassword}
        onLogin={handleLogin}
        onRegister={handleRegister}
      />
    );
  }

  const propsTela = {
    movimentacoes,
    contasAPagar,
    metasLista,
    totalEntradas,
    totalSaidas,
    saldoTotal,
    totalEmAberto,
    totalPago,
    formatMoney,

    onNovaConta: () => setModalContaVisible(true),
    onNovaMovimentacao: () => setModalMovVisible(true),
    onNovaMeta: () => setModalMetaVisible(true),

    onPagarConta: handlePagarConta,
    onOcultarConta: handleOcultarConta,

    onDepositarMeta: handleDepositarMeta,
    onResgatarMeta: handleResgatarMeta,
    onExcluirMeta: handleExcluirMeta,

    onEditarMovimentacao: abrirEdicaoMovimentacao,
    onExcluirMovimentacao: excluirMovimentacao,

    onNavigate: setScreen,
  };

  let telaAtual;

  switch (screen) {
    case 'Contas':
    case 'Pendências':
      telaAtual = <Contas {...propsTela} />;
      break;

    case 'Gráfico':
      telaAtual = <Grafico {...propsTela} />;
      break;

    case 'Saldo':
      telaAtual = <Saldo {...propsTela} />;
      break;

    case 'Metas':
      telaAtual = <Metas {...propsTela} />;
      break;

    case 'Relatório':
      telaAtual = <Relatorio {...propsTela} />;
      break;

    case 'Geral':
    default:
      telaAtual = <Geral {...propsTela} />;
      break;
  }

  return (
    <SafeAreaView style={styles.dashboardContainer}>
      <StatusBar barStyle="dark-content" backgroundColor="#FFFFFF" />

      <Topbar
        screen={screen}
        email={usuarioLogado?.email || ''}
        nomeUsuario={usuarioLogado?.nome || ''}
        onSaveName={handleSaveName}
        onChangePassword={handleChangePassword}
        onLogout={handleLogout}
      />

      <ScrollView
        contentContainerStyle={styles.scrollBody}
        showsVerticalScrollIndicator={false}>
        {telaAtual}
      </ScrollView>

      <CadastroModal
        tipo="conta"
        visible={modalContaVisible}
        onClose={() => setModalContaVisible(false)}
        onSave={handleAddConta}
        novaDescricao={novaDescricao}
        setNovaDescricao={setNovaDescricao}
        novoValor={novoValor}
        setNovoValor={setNovoValor}
        novaContaOrg={novaContaOrg}
        setNovaContaOrg={setNovaContaOrg}
        novoVencimento={novoVencimento}
        setNovoVencimento={setNovoVencimento}
      />

      <CadastroModal
        tipo="movimentacao"
        visible={modalMovVisible}
        onClose={() => setModalMovVisible(false)}
        onSave={handleAddMovimentacao}
        novaDescricao={novaDescricao}
        setNovaDescricao={setNovaDescricao}
        novoValor={novoValor}
        setNovoValor={setNovoValor}
        tipoMov={tipoMov}
        setTipoMov={setTipoMov}
        formaPagamento={formaPagamento}
        setFormaPagamento={setFormaPagamento}
      />

      <CadastroModal
        tipo="movimentacao"
        visible={modalEditarMovVisible}
        onClose={() => {
          setModalEditarMovVisible(false);
          setMovimentacaoEditando(null);
          limparForm();
        }}
        onSave={handleEditarMovimentacao}
        novaDescricao={novaDescricao}
        setNovaDescricao={setNovaDescricao}
        novoValor={novoValor}
        setNovoValor={setNovoValor}
        tipoMov={tipoMov}
        setTipoMov={setTipoMov}
        formaPagamento={formaPagamento}
        setFormaPagamento={setFormaPagamento}
      />

      <CadastroModal
        tipo="meta"
        visible={modalMetaVisible}
        onClose={() => setModalMetaVisible(false)}
        onSave={handleAddMeta}
        novaDescricao={novaDescricao}
        setNovaDescricao={setNovaDescricao}
        novoValor={novoValor}
        setNovoValor={setNovoValor}
        novaContaOrg={novaContaOrg}
        setNovaContaOrg={setNovaContaOrg}
      />

      <BottomNav screen={screen} onNavigate={setScreen} />
    </SafeAreaView>
  );
}