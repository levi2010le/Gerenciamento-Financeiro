import * as SQLite from 'expo-sqlite';

let database;

export async function openDatabase() {
  if (!database) {
    database = await SQLite.openDatabaseAsync('financeiro.db');
  }

  return database;
}

export async function initDatabase() {
  const db = await openDatabase();

  await db.execAsync(`
    PRAGMA journal_mode = WAL;
    PRAGMA foreign_keys = ON;

    CREATE TABLE IF NOT EXISTS usuarios (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nome TEXT NOT NULL,
      email TEXT NOT NULL UNIQUE,
      senha TEXT NOT NULL,
      criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS bancos (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      usuario_id INTEGER NOT NULL,
      nome TEXT NOT NULL,
      saldo REAL NOT NULL DEFAULT 0,
      moeda TEXT NOT NULL DEFAULT 'BRL',
      FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS categorias (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tipo TEXT NOT NULL
        CHECK (tipo IN ('ENTRADA', 'SAIDA')),
      nome TEXT NOT NULL,
      descricao TEXT
    );

    CREATE TABLE IF NOT EXISTS transacoes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      usuario_id INTEGER NOT NULL,
      banco_id INTEGER NOT NULL,
      categoria_id INTEGER,
      tipo TEXT NOT NULL
        CHECK (tipo IN ('ENTRADA', 'SAIDA')),
      descricao TEXT NOT NULL,
      valor REAL NOT NULL CHECK (valor > 0),
      data TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      tipo_pagamento TEXT NOT NULL,
      FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
      FOREIGN KEY (banco_id)
        REFERENCES bancos(id),
      FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS contas (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      usuario_id INTEGER NOT NULL,
      banco_id INTEGER NOT NULL,
      descricao TEXT NOT NULL,
      valor REAL NOT NULL CHECK (valor > 0),
      tipo_pagamento TEXT NOT NULL,
      vencimento TEXT NOT NULL,
      status TEXT NOT NULL DEFAULT 'PENDENTE'
        CHECK (status IN ('PENDENTE', 'PAGA')),
      data_pagamento TEXT,
      criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
      FOREIGN KEY (banco_id)
        REFERENCES bancos(id)
    );

    CREATE TABLE IF NOT EXISTS metas (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      usuario_id INTEGER NOT NULL,
      banco_id INTEGER,
      titulo TEXT NOT NULL,
      descricao TEXT,
      valor_meta REAL NOT NULL CHECK (valor_meta > 0),
      valor_atual REAL NOT NULL DEFAULT 0 CHECK (valor_atual >= 0),
      data_inicio TEXT NOT NULL,
      prazo TEXT,
      status TEXT NOT NULL DEFAULT 'EM_ANDAMENTO'
        CHECK (status IN ('EM_ANDAMENTO', 'CONCLUIDA', 'CANCELADA')),
      revisao_gastos TEXT,
      criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
      FOREIGN KEY (banco_id)
        REFERENCES bancos(id)
    );

    CREATE INDEX IF NOT EXISTS idx_bancos_usuario
      ON bancos(usuario_id);

    CREATE INDEX IF NOT EXISTS idx_transacoes_usuario
      ON transacoes(usuario_id);

    CREATE INDEX IF NOT EXISTS idx_transacoes_banco
      ON transacoes(banco_id);

    CREATE INDEX IF NOT EXISTS idx_transacoes_data
      ON transacoes(data);

    CREATE INDEX IF NOT EXISTS idx_contas_usuario
      ON contas(usuario_id);

    CREATE INDEX IF NOT EXISTS idx_contas_vencimento
      ON contas(vencimento);

    CREATE INDEX IF NOT EXISTS idx_metas_usuario
      ON metas(usuario_id);
  `);

  return db;
}