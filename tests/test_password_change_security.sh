#!/bin/bash

# Script de teste para validar invalidação de tokens ao trocar senha
# Execução: bash test_password_change_security.sh

BASE_URL="http://localhost/api/v1"
EMAIL="admin@example.com"
OLD_PASSWORD="password123"
NEW_PASSWORD="password1234"

echo "🔐 Teste de Segurança: Invalidação de Tokens ao Trocar Senha"
echo "=============================================================="
echo ""

# Passo 1: Fazer login
echo "📝 Passo 1: Fazendo login com senha atual..."
LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${OLD_PASSWORD}\"}")

ACCESS_TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
REFRESH_TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"refresh_token":"[^"]*' | cut -d'"' -f4)
USER_ID=$(echo $LOGIN_RESPONSE | grep -o '"id":[0-9]*' | cut -d':' -f2 | head -1)

if [ -z "$ACCESS_TOKEN" ]; then
    echo "❌ Falha no login!"
    echo "Resposta: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Login bem-sucedido"
echo "   User ID: $USER_ID"
echo "   Access Token: ${ACCESS_TOKEN:0:20}..."
echo "   Refresh Token: ${REFRESH_TOKEN:0:20}..."
echo ""

# Passo 2: Trocar senha
echo "📝 Passo 2: Trocando senha..."
CHANGE_RESPONSE=$(curl -s -X PUT "${BASE_URL}/users/${USER_ID}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -d "{\"current_password\":\"${OLD_PASSWORD}\",\"password\":\"${NEW_PASSWORD}\"}")

if echo "$CHANGE_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Senha alterada com sucesso"
else
    echo "❌ Falha ao trocar senha!"
    echo "Resposta: $CHANGE_RESPONSE"
    exit 1
fi
echo ""

# Passo 3: Tentar usar refresh token antigo (DEVE FALHAR)
echo "📝 Passo 3: Tentando usar refresh token ANTIGO (deve falhar)..."
REFRESH_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/refresh" \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"${REFRESH_TOKEN}\"}")

if echo "$REFRESH_RESPONSE" | grep -q '"success":false'; then
    echo "✅ Refresh token foi REVOGADO corretamente (segurança OK)"
    echo "   Mensagem: $(echo $REFRESH_RESPONSE | grep -o '"message":"[^"]*' | cut -d'"' -f4)"
else
    echo "❌ FALHA DE SEGURANÇA: Refresh token ainda funciona!"
    echo "Resposta: $REFRESH_RESPONSE"
fi
echo ""

# Passo 4: Tentar login com senha antiga (DEVE FALHAR)
echo "📝 Passo 4: Tentando login com senha ANTIGA (deve falhar)..."
OLD_LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${OLD_PASSWORD}\"}")

if echo "$OLD_LOGIN_RESPONSE" | grep -q '"success":false'; then
    echo "✅ Login com senha antiga FALHOU corretamente"
    echo "   Mensagem: $(echo $OLD_LOGIN_RESPONSE | grep -o '"message":"[^"]*' | cut -d'"' -f4)"
else
    echo "❌ PROBLEMA: Login com senha antiga ainda funciona!"
    echo "Resposta: $OLD_LOGIN_RESPONSE"
fi
echo ""

# Passo 5: Login com nova senha (DEVE FUNCIONAR)
echo "📝 Passo 5: Tentando login com senha NOVA (deve funcionar)..."
NEW_LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${EMAIL}\",\"password\":\"${NEW_PASSWORD}\"}")

if echo "$NEW_LOGIN_RESPONSE" | grep -q '"access_token"'; then
    echo "✅ Login com senha nova FUNCIONOU corretamente"
    NEW_ACCESS_TOKEN=$(echo $NEW_LOGIN_RESPONSE | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
    echo "   Novo Access Token: ${NEW_ACCESS_TOKEN:0:20}..."
else
    echo "❌ Falha ao fazer login com senha nova!"
    echo "Resposta: $NEW_LOGIN_RESPONSE"
fi
echo ""

# Passo 6: Reverter senha (para não quebrar outros testes)
echo "📝 Passo 6: Revertendo senha para valor original..."
REVERT_RESPONSE=$(curl -s -X PUT "${BASE_URL}/users/${USER_ID}" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${NEW_ACCESS_TOKEN}" \
  -d "{\"current_password\":\"${NEW_PASSWORD}\",\"password\":\"${OLD_PASSWORD}\"}")

if echo "$REVERT_RESPONSE" | grep -q '"success":true'; then
    echo "✅ Senha revertida para ${OLD_PASSWORD}"
else
    echo "⚠️  Aviso: Não foi possível reverter senha"
fi
echo ""

echo "=============================================================="
echo "🎉 Teste Completo!"
echo ""
echo "Resumo dos resultados:"
echo "  ✅ = Comportamento correto"
echo "  ❌ = Problema de segurança"
