#!/bin/bash

# Script de diagnostic complet pour les pods MySQL
# Génère un rapport détaillé des problèmes potentiels

NAMESPACE="e-commerce"
OUTPUT_DIR="./mysql-diagnostics-$(date +%Y%m%d-%H%M%S)"

echo "🔧 Diagnostic des pods MySQL"
echo "================================================"
echo "Namespace: $NAMESPACE"
echo "Rapport dans: $OUTPUT_DIR"
echo ""

mkdir -p "$OUTPUT_DIR"

# 1. État général des pods
echo "📊 1. État général des pods MySQL..."
kubectl get pods -n $NAMESPACE | grep mysql > "$OUTPUT_DIR/01-pods-status.txt"
cat "$OUTPUT_DIR/01-pods-status.txt"
echo ""

# 2. Événements récents
echo "📋 2. Événements récents (30 derniers)..."
kubectl get events -n $NAMESPACE --sort-by='.lastTimestamp' | grep mysql | tail -30 > "$OUTPUT_DIR/02-events.txt"
cat "$OUTPUT_DIR/02-events.txt"
echo ""

# 3. Analyse détaillée de chaque pod
echo "🔍 3. Analyse détaillée par pod..."
for POD in $(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | awk '{print $1}'); do
    echo "  Analyse de $POD..."

    # Status JSON complet
    kubectl get pod $POD -n $NAMESPACE -o json > "$OUTPUT_DIR/pod-${POD}.json"

    # Extraire informations critiques
    {
        echo "=== POD: $POD ==="
        echo ""
        echo "Status:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.phase'
        echo ""

        echo "Restart Count:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.containerStatuses[0].restartCount'
        echo ""

        echo "Last State:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.containerStatuses[0].lastState | if . == {} then "Never restarted" else .terminated end'
        echo ""

        echo "Current State:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.containerStatuses[0].state'
        echo ""

        echo "Resource Requests/Limits:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.spec.containers[0].resources'
        echo ""

        echo "Conditions:"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.conditions[] | "\(.type): \(.status) - \(.reason // "N/A")"'
        echo ""

    } > "$OUTPUT_DIR/pod-${POD}-summary.txt"

    # Logs actuels
    kubectl logs $POD -n $NAMESPACE --tail=100 > "$OUTPUT_DIR/pod-${POD}-logs.txt" 2>&1

    # Logs précédents si le pod a redémarré
    RESTARTS=$(kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.containerStatuses[0].restartCount')
    if [ "$RESTARTS" -gt 0 ]; then
        echo "    └─ $RESTARTS redémarrage(s) détecté(s), récupération des logs précédents..."
        kubectl logs $POD -n $NAMESPACE --previous > "$OUTPUT_DIR/pod-${POD}-logs-previous.txt" 2>&1
    fi

    # Describe complet
    kubectl describe pod $POD -n $NAMESPACE > "$OUTPUT_DIR/pod-${POD}-describe.txt"
done

echo ""

# 4. Vérifier les problèmes OOM
echo "💾 4. Recherche de problèmes OOM (Out of Memory)..."
{
    echo "=== OOM ANALYSIS ==="
    for POD in $(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | awk '{print $1}'); do
        OOM_CHECK=$(kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.containerStatuses[0].lastState.terminated.reason // "none"')
        if [[ "$OOM_CHECK" == "OOMKilled" ]]; then
            echo "🔴 $POD: OOMKilled détecté!"
            echo "   Memory Limit: $(kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.spec.containers[0].resources.limits.memory')"
        fi
    done
} > "$OUTPUT_DIR/04-oom-analysis.txt"
cat "$OUTPUT_DIR/04-oom-analysis.txt"
echo ""

# 5. Vérifier les CrashLoopBackOff
echo "🔁 5. Recherche de CrashLoopBackOff..."
{
    echo "=== CRASH LOOP ANALYSIS ==="
    kubectl get pods -n $NAMESPACE --no-headers | grep mysql | while read line; do
        STATUS=$(echo $line | awk '{print $3}')
        POD=$(echo $line | awk '{print $1}')
        if [[ "$STATUS" == *"CrashLoop"* ]] || [[ "$STATUS" == *"Error"* ]]; then
            echo "🔴 $POD: Status=$STATUS"
            echo "   Derniers logs:"
            kubectl logs $POD -n $NAMESPACE --tail=20 2>&1 | sed 's/^/   /'
        fi
    done
} > "$OUTPUT_DIR/05-crashloop-analysis.txt"
cat "$OUTPUT_DIR/05-crashloop-analysis.txt"
echo ""

# 6. Analyser les probes qui échouent
echo "🏥 6. Analyse des health probes..."
{
    echo "=== HEALTH PROBES ANALYSIS ==="
    for POD in $(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | awk '{print $1}'); do
        echo "Pod: $POD"
        kubectl get pod $POD -n $NAMESPACE -o json | jq -r '.status.conditions[] | select(.type=="Ready" or .type=="ContainersReady") | "  \(.type): \(.status) - \(.message // "OK")"'
        echo ""
    done
} > "$OUTPUT_DIR/06-probes-analysis.txt"
cat "$OUTPUT_DIR/06-probes-analysis.txt"
echo ""

# 7. Résumé final
echo "📝 7. Génération du résumé..."
{
    echo "=== RÉSUMÉ DU DIAGNOSTIC ==="
    echo "Date: $(date)"
    echo "Namespace: $NAMESPACE"
    echo ""

    TOTAL_PODS=$(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | wc -l)
    RUNNING_PODS=$(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | grep -c "Running")
    TOTAL_RESTARTS=$(kubectl get pods -n $NAMESPACE --no-headers | grep mysql | awk '{sum+=$4} END {print sum}')

    echo "Pods MySQL:"
    echo "  Total: $TOTAL_PODS"
    echo "  Running: $RUNNING_PODS"
    echo "  Total Redémarrages: ${TOTAL_RESTARTS:-0}"
    echo ""

    if [ "${TOTAL_RESTARTS:-0}" -gt 0 ]; then
        echo "⚠️  ATTENTION: Des redémarrages ont été détectés"
        echo ""
        echo "Pods avec redémarrages:"
        kubectl get pods -n $NAMESPACE --no-headers | grep mysql | awk '$4 > 0 {print "  - "$1" : "$4" redémarrage(s)"}'
        echo ""
    fi

    OOM_COUNT=$(grep -c "OOMKilled" "$OUTPUT_DIR"/04-oom-analysis.txt 2>/dev/null || echo 0)
    if [ "$OOM_COUNT" -gt 0 ]; then
        echo "🔴 PROBLÈME MÉMOIRE DÉTECTÉ: $OOM_COUNT pod(s) tué(s) par OOM"
        echo "   Recommandation: Augmenter les limites mémoire dans les fichiers YAML"
        echo ""
    fi

    CRASH_COUNT=$(grep -c "🔴" "$OUTPUT_DIR"/05-crashloop-analysis.txt 2>/dev/null || echo 0)
    if [ "$CRASH_COUNT" -gt 0 ]; then
        echo "🔴 CRASHLOOP DÉTECTÉ: $CRASH_COUNT pod(s) en erreur"
        echo "   Recommandation: Vérifier les logs dans $OUTPUT_DIR/pod-*-logs.txt"
        echo ""
    fi

    if [ "${TOTAL_RESTARTS:-0}" -eq 0 ] && [ "$RUNNING_PODS" -eq "$TOTAL_PODS" ]; then
        echo "✅ SYSTÈME SAIN: Tous les pods fonctionnent correctement sans redémarrage"
    fi

} > "$OUTPUT_DIR/00-summary.txt"
cat "$OUTPUT_DIR/00-summary.txt"

echo ""
echo "================================================"
echo "✅ Diagnostic terminé"
echo "📁 Rapports générés dans: $OUTPUT_DIR"
echo ""
echo "Fichiers importants:"
echo "  - 00-summary.txt : Résumé global"
echo "  - 04-oom-analysis.txt : Problèmes mémoire"
echo "  - 05-crashloop-analysis.txt : Problèmes de crash"
echo "  - pod-*-logs.txt : Logs de chaque pod"
echo ""
