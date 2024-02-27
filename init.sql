SET client_encoding = 'UTF8';
SET client_min_messages = warning;
SET row_security = off;

CREATE UNLOGGED TABLE IF NOT EXISTS clientes(
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    limite INTEGER NOT NULL,
    saldo INTEGER DEFAULT 0 NOT NULL
);

CREATE UNLOGGED TABLE IF NOT EXISTS transacoes(
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL,
    valor INTEGER NOT NULL,
    tipo CHAR(1) NOT NULL,
    descricao VARCHAR(10),
    realizada_em TIMESTAMP DEFAULT current_timestamp,
    CONSTRAINT fk_clientes_transacoes_id
		FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE INDEX transacoes_cliente_id_idx ON transacoes (cliente_id, realizada_em DESC);

CREATE OR REPLACE PROCEDURE gerencia_transacoes(
  p_cliente_id INTEGER
)
LANGUAGE plpgsql
AS $$
BEGIN
  DELETE FROM transacoes WHERE cliente_id = p_cliente_id AND id NOT IN (
    SELECT id FROM transacoes WHERE cliente_id = p_cliente_id ORDER by id DESC LIMIT 10);
END;
$$;

DO $$
BEGIN
  INSERT INTO clientes (nome, limite)
  VALUES
    ('o barato sai caro', 1000 * 100),
    ('zan corp ltda', 800 * 100),
    ('les cruders', 10000 * 100),
    ('padaria joia de cocaia', 100000 * 100),
    ('kid mais', 5000 * 100);
END;
$$;