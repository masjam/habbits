export function usePerformanceScore(rubric) {
    const scaleMin = Number(rubric?.scale?.min ?? 1)
    const scaleMax = Number(rubric?.scale?.max ?? 5)
    const multiplier = Number(rubric?.scale?.multiplier ?? 20)

    const resolvePredicate = (score) => {
        const rules = rubric?.predicates || [
            { min: 90, label: 'Sangat Baik' },
            { min: 75, label: 'Baik' },
            { min: 60, label: 'Cukup' },
            { min: 0, label: 'Kurang' },
        ]
        for (const r of rules) {
            if (Number(score) >= Number(r.min)) {
                return r.label
            }
        }
        return 'Kurang'
    }

    const predicateVariant = (predicate) => {
        switch (predicate) {
            case 'Sangat Baik': return 'success'
            case 'Baik': return 'info'
            case 'Cukup': return 'warning'
            default: return 'danger'
        }
    }

    const calculate = (category, indicatorScores) => {
        const aspects = rubric?.categories?.[category]?.aspects || {}
        const aspectScores = {}
        let weightedSum = 0
        let totalWeight = 0
        let filledCount = 0
        let totalIndicators = 0

        for (const [aspectKey, meta] of Object.entries(aspects)) {
            const indicators = meta.indicators || []
            const submitted = indicatorScores?.[aspectKey] || {}
            const vals = []

            for (const ind of indicators) {
                totalIndicators++
                const raw = submitted[ind.code]
                if (raw !== undefined && raw !== null && raw !== '') {
                    const num = Number(raw)
                    if (Number.isInteger(num) && num >= scaleMin && num <= scaleMax) {
                        vals.push(num * multiplier)
                        filledCount++
                    }
                }
            }

            const avg = vals.length > 0
                ? Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 100) / 100
                : 0
            aspectScores[aspectKey] = avg

            const weight = Number(meta.weight ?? 0.25)
            weightedSum += avg * weight
            totalWeight += weight
        }

        const finalScore = totalWeight > 0
            ? Math.round((weightedSum / totalWeight) * 100) / 100
            : 0

        return {
            aspectScores,
            finalScore,
            predicate: resolvePredicate(finalScore),
            isComplete: totalIndicators > 0 && filledCount === totalIndicators,
            filledCount,
            totalIndicators,
        }
    }

    return {
        calculate,
        resolvePredicate,
        predicateVariant,
    }
}
