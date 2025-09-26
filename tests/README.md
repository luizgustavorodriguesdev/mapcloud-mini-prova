
# Testes manuais sugeridos

1. `POST /webhook/nfe-upload` com `sample_data/nfe_exemplo.xml` deve criar a entrega e evento inicial.
2. `POST /webhook/evento` com `sample_data/webhook_exemplo.json` deve registrar evento e permitir visualização em `/api/rastreamento?chave=...`.
3. `GET /api/entregas` deve listar com paginação.
4. Front (public/index.html) deve buscar por chave e mostrar mapa com destino + posições.
