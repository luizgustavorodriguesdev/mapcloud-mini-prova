
# Mini‑Projeto – Prova Técnica (PHP 5.2/5.3 + MySQL + JS + APIs + Mapas)

> **Objetivo:** avaliar sua capacidade de construir um mini sistema de rastreamento de entregas, consumindo/produzindo APIs (JSON/XML), persistindo em MySQL e exibindo um mapa interativo.
> **Stack obrigatória:** PHP 5.2/5.3 (sem frameworks), MySQL, HTML/CSS/JS (vanilla), Leaflet + OpenStreetMap.
> **Tempo sugerido:** 6–8 horas (não rígido).

---

## Entregável

Um pequeno app com:
1) **Ingestão de NF‑e (XML)** via upload e parsing (chave, emitente, destinatário, CEP, valor, data de emissão).
2) **Webhooks**: endpoint que recebe eventos JSON de “atualização de entrega” (status e coordenadas).
3) **APIs REST** próprias:
   - `GET /api/entregas?status=...` → lista JSON paginada.
   - `GET /api/rastreamento?chave=...` → detalhes + histórico da entrega (JSON).
4) **Front‑end**:
   - Página com **busca por chave da NF‑e** e **timeline** (NF→Romaneio→CT‑e→Entrega) destacando o **gargalo**.
   - **Mapa** (Leaflet) que plota posições recebidas pelo webhook e o endereço do destinatário (geocodificado).
5) **Banco de dados** (MySQL): use o schema base em `backend/db.sql` e ajuste o que precisar.

### Restrições

- PHP 5.2/5.3 compatível: **sem namespaces, sem composer, sem traits**. Use **MySQLi** (ou PDO se disponível), **prepared statements**.
- Código **simples e seguro**: valide entradas, sanitize uploads, trate erros.
- **Sem dependências servidoras** além de PHP + MySQL. (No front, pode usar **Leaflet** e **OSM** via CDN.)

---

## Tarefas detalhadas

### 1) Upload e parsing de NF‑e (XML)

- Rota: `POST /webhook/nfe-upload` (multipart/form-data, campo `xml`).
- Parse mínimo do XML (veja exemplo em `sample_data/nfe_exemplo.xml`): 
  - `chave`, `emitente.cnpj`, `destinatario.cnpj`, `destinatario.nome`, `destinatario.cep`,
  - `valor_nota`, `data_emissao`.
- Persistir em `entregas` (se não existir) e `eventos` (registrar “NF‑e recebida”).

### 2) Webhook de eventos (JSON)

- Rota: `POST /webhook/evento` (JSON). Exemplo em `sample_data/webhook_exemplo.json`.
- Campos: `chave`, `status` (ex.: `EM_TRANSITO`, `ENTREGUE`, `DEVOLVIDA`), `lat`, `lng`, `observacao`, `data_hora`.
- Gravar em `eventos`; quando status for terminal (`ENTREGUE`/`DEVOLVIDA`) atualizar `entregas.status_atual`.

### 3) Integração de CEP → coordenadas

- Ao inserir/atualizar uma entrega com CEP, **consultar CEP** (pode usar **ViaCEP**) e **geocodificar** o logradouro para lat/lng. 
- Sem chave paga: use **Nominatim** (OSM). Se preferir offline para a prova, pode **mockar** a geocodificação usando `sample_data/geocode_stub.json`.
- Salvar `dest_lat`, `dest_lng` em `entregas` (se houver).

### 4) APIs próprias

- `GET /api/entregas?status=&page=&limit=`: retorna JSON com paginação, filtros por `status` e período (`de`, `ate`).
- `GET /api/rastreamento?chave=`: retorna JSON com dados da entrega + array de eventos ordenado por data.
- `GET /api/metricas/gargalo?de=&ate=`: tempos médios entre etapas; devolver etapa de maior tempo (gargalo).

### 5) Front‑end (index.html)

- Campo para buscar por **chave da NF‑e** (input + botão).
- **Timeline** dinâmica com 4 marcos: NF, Romaneio, CT‑e, Entrega.
- **Mapa** (Leaflet) mostrando:
  - marcador do **destinatário**;
  - polilinha das **posições** (eventos com lat/lng).
- Cards com KPIs: total entregas no período, % entregues no prazo, tempo médio por etapa, **gargalo**.
- UX: loading, toasts de erro, responsivo; botão **Topo**.

---

## Banco de Dados

Veja `backend/db.sql` (base mínima). Pode alterar/adicionar índices, fks e colunas se justificar no README.

---

## Como rodar

1. Crie um banco MySQL e rode `backend/db.sql`.
2. Copie tudo para um host com PHP 5.2/5.3 e MySQLi habilitado.
3. Ajuste `backend/config.php` com as credenciais.
4. Acesse `public/index.html` em um servidor web (ou `public/index.php` se preferir render dinâmico).
5. Envie `POST /webhook/nfe-upload` com o XML de teste, depois `POST /webhook/evento` com JSON de exemplo.
6. Teste as APIs (`/api/...`) e a UI.

---

## Avaliação (Rubrica)

- **Corretude (30%)**: APIs respondem conforme especificado, parsing XML correto, dados persistidos.
- **Qualidade de código (25%)**: organização, PHP 5.2/5.3 compatível, segurança básica (validação, prepared, CSRF onde cabível).
- **Modelo de dados (15%)**: chaves/índices adequados, normalização razoável.
- **Front‑end/UX (15%)**: mapa funcional, timeline clara, responsivo, feedback de loading/erros.
- **Integrações (10%)**: geocodificação/CEP (real ou mock) integrada e tratada.
- **Desempenho (5%)**: paginação, índices, sem N+1, sem loops desnecessários.

---

## Entrega do candidato

- Link para repositório (ou ZIP) contendo o código.
- Instruções rápidas de deploy.
- Capturas de tela ou GIF curto do fluxo principal.
- Opcional: testes simples (ver pasta `tests/`).

Boa sorte! 🚀
