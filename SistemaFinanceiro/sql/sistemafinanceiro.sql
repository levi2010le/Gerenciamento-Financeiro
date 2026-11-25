CREATE DATABASE controle_financeiro;
 
USE controle_financeiro;
 
CREATE TABLE tb_usuario (
    cd_usuario INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nm_usuario VARCHAR(45) NOT NULL,
    ds_senha VARCHAR(255) NOT NULL,
    ds_email VARCHAR(100) NOT NULL UNIQUE,
    dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
 
CREATE TABLE tb_banco (
    cd_banco INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nm_banco VARCHAR(45) NOT NULL,
    ds_saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ds_moeda VARCHAR(10) NOT NULL DEFAULT 'BRL',
    id_usuario INT NOT NULL,
 
    CONSTRAINT fk_banco_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES tb_usuario(cd_usuario)
);
 
CREATE TABLE tb_categoria (
    cd_categoria INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tp_transferencia ENUM('ENTRADA', 'SAIDA') NOT NULL,
    nm_categoria VARCHAR(100) NOT NULL,
    ds_categoria VARCHAR(250)
);
 
CREATE TABLE tb_entrada (
    cd_entrada INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vl_entrada DECIMAL(12,2) NOT NULL,
    dt_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_categoria INT NOT NULL,
    ds_entrada VARCHAR(250) NOT NULL,
    id_banco INT NOT NULL,
 
    CONSTRAINT chk_entrada_valor CHECK (vl_entrada > 0),
 
    CONSTRAINT fk_entrada_categoria  FOREIGN KEY (id_categoria) REFERENCES tb_categoria(cd_categoria),
    CONSTRAINT fk_entrada_banco FOREIGN KEY (id_banco) REFERENCES tb_banco(cd_banco)
);
 
CREATE TABLE tb_saida (
    cd_saida INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vl_saida DECIMAL(12,2) NOT NULL,
    dt_saida DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_categoria INT NOT NULL,
    ds_saida VARCHAR(250) NOT NULL,
    id_banco INT NOT NULL,
 
    CONSTRAINT chk_saida_valor CHECK (vl_saida > 0),
    CONSTRAINT fk_saida_categoria FOREIGN KEY (id_categoria) REFERENCES tb_categoria(cd_categoria),
    CONSTRAINT fk_saida_banco FOREIGN KEY (id_banco) REFERENCES tb_banco(cd_banco)
);
 
 
CREATE TABLE tb_meta (
    cd_meta INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nm_meta VARCHAR(100) NOT NULL,
    ds_meta VARCHAR(250),
    vl_objetivo DECIMAL(12,2) NOT NULL,
    vl_atual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    dt_inicio DATE NOT NULL,
    dt_prazo DATE,
    st_meta ENUM(
        'EM_ANDAMENTO',
        'CONCLUIDA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'EM_ANDAMENTO',
    id_usuario INT NOT NULL,
    id_banco INT NULL,
    dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    CONSTRAINT chk_meta_objetivo CHECK (vl_objetivo > 0),
    CONSTRAINT chk_meta_atual CHECK (vl_atual >= 0),
    CONSTRAINT fk_meta_usuario FOREIGN KEY (id_usuario) REFERENCES tb_usuario(cd_usuario),
    CONSTRAINT fk_meta_banco FOREIGN KEY (id_banco)  REFERENCES tb_banco(cd_banco)
);

ALTER TABLE tb_entrada
ADD COLUMN tp_pagamento VARCHAR(20) NOT NULL;

ALTER TABLE	 tb_saida
ADD COLUMN tp_pagamento VARCHAR(20) NOT NULL;

ALTER TABLE tb_entrada
MODIFY COLUMN id_categoria INT NULL;

ALTER TABLE tb_saida
MODIFY COLUMN id_categoria INT NULL;

CREATE TABLE tb_conta_pagar (
    cd_conta INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_banco INT NOT NULL,
    ds_descricao VARCHAR(250) NOT NULL,
    vl_conta DECIMAL(12,2) NOT NULL,
    tp_pagamento VARCHAR(20) NOT NULL,
    dt_vencimento DATE NOT NULL,
    st_conta ENUM('PENDENTE', 'PAGA') NOT NULL DEFAULT 'PENDENTE',
    dt_pagamento DATETIME NULL,	
    dt_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_conta_pagar_valor CHECK (vl_conta > 0),
    CONSTRAINT fk_conta_pagar_usuario
        FOREIGN KEY (id_usuario) REFERENCES tb_usuario(cd_usuario),
    CONSTRAINT fk_conta_pagar_banco
        FOREIGN KEY (id_banco) REFERENCES tb_banco(cd_banco)
);

ALTER TABLE tb_meta
ADD COLUMN ds_revisao_gastos TEXT NULL;

ALTER TABLE tb_entrada MODIFY COLUMN id_categoria INT NULL;
ALTER TABLE tb_saida MODIFY COLUMN id_categoria INT NULL;

ALTER TABLE tb_entrada
MODIFY COLUMN id_categoria INT NULL;

