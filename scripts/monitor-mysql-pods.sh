#!/bin/bash

# Script de surveillance des pods MySQL
# Surveille les redémarrages, crashloops et problèmes OOM

NAMESPACE="e-commerce"
INTERVAL=10  # Vérifier toutes les 10 secondes

echo "🔍 Surveillance des pods MySQL dans le namespace $NAMESPACE"
echo "Intervalle: ${INTERVAL}s"
echo "================================================"
echo ""

while true; do
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

    # Récupérer les pods MySQL
    PODS=$(kubectl get pods -n $NAMESPACE --no-headers | grep mysql)

    if [ -z "$PODS" ]; then
        echo "[$TIMESTAMP] ⚠️  Aucun pod MySQL trouvé"
        sleep $INTERVAL
        continue
    fi

    # Vérifier chaque pod
    while IFS= read -r line; do
        POD_NAME=$(echo $line | awk '{print $1}')
        STATUS=$(echo $line | awk '{print $3}')
        RESTARTS=$(echo $line | awk '{print $4}')
        AGE=$(echo $line | awk '{print $5}')

        # Alertes conditionnelles
        if [ "$RESTARTS" -gt 0 ]; then
            echo "[$TIMESTAMP] 🔴 ALERTE: $POD_NAME - $RESTARTS redémarrage(s) | Status: $STATUS"

            # Récupérer la raison du dernier redémarrage
            LAST_STATE=$(kubectl get pod $POD_NAME -n $NAMESPACE -o json 2>/dev/null | \
                jq -r '.status.containerStatuses[0].lastState.terminated.reason // "Unknown"')

            if [ "$LAST_STATE" != "Unknown" ] && [ "$LAST_STATE" != "null" ]; then
                echo "    └─ Raison: $LAST_STATE"
            fi

            # Vérifier si OOMKilled
            if [[ "$LAST_STATE" == *"OOMKilled"* ]]; then
                echo "    └─ ⚠️  PROBLÈME MÉMOIRE DÉTECTÉ (OOMKilled)"
                echo "    └─ Affichage des derniers logs:"
                kubectl logs $POD_NAME -n $NAMESPACE --tail=20 --previous 2>/dev/null | sed 's/^/       /'
            fi
        elif [[ "$STATUS" == "CrashLoopBackOff" ]]; then
            echo "[$TIMESTAMP] 🔴 CRASH: $POD_NAME - En boucle de crash"
            kubectl logs $POD_NAME -n $NAMESPACE --tail=30 2>/dev/null | sed 's/^/    /'
        elif [[ "$STATUS" != "Running" ]] && [[ "$STATUS" != "Completed" ]]; then
            echo "[$TIMESTAMP] 🟡 ATTENTION: $POD_NAME - Status anormal: $STATUS"
        fi
    done <<< "$PODS"

    # Afficher un résumé toutes les 60 secondes
    if [ $(($(date +%s) % 60)) -lt $INTERVAL ]; then
        RUNNING=$(echo "$PODS" | grep -c "Running")
        TOTAL=$(echo "$PODS" | wc -l | tr -d ' ')
        TOTAL_RESTARTS=$(echo "$PODS" | awk '{sum+=$4} END {print sum}')

        echo "[$TIMESTAMP] ✅ Résumé: $RUNNING/$TOTAL pods Running | Total redémarrages: ${TOTAL_RESTARTS:-0}"
    fi

    sleep $INTERVAL
done
