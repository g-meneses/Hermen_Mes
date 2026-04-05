ALTER TABLE lote_wip
    ADD COLUMN id_lote_padre INT NULL AFTER id_lote_wip,
    ADD KEY idx_lote_wip_padre (id_lote_padre),
    ADD CONSTRAINT fk_lote_wip_padre
        FOREIGN KEY (id_lote_padre) REFERENCES lote_wip (id_lote_wip);

ALTER TABLE movimientos_wip
    ADD COLUMN id_lote_relacionado INT NULL AFTER id_lote_wip,
    ADD KEY idx_mov_wip_relacionado (id_lote_relacionado),
    ADD CONSTRAINT fk_mov_wip_relacionado
        FOREIGN KEY (id_lote_relacionado) REFERENCES lote_wip (id_lote_wip);
