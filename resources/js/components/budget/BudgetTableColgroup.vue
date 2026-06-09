<script setup lang="ts">
defineProps<{
    layout: 'summary' | 'category' | 'pocket';
    period: 'monthly' | 'yearly';
}>();
</script>

<template>
    <colgroup v-if="layout === 'summary' && period === 'monthly'">
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-amount" />
        <col class="budget-col-progress" />
    </colgroup>
    <colgroup v-else-if="layout === 'summary' && period === 'yearly'">
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-amount" />
        <col class="budget-col-forecast" />
        <col class="budget-col-progress" />
    </colgroup>
    <colgroup v-else-if="layout === 'category' && period === 'monthly'">
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-amount" />
        <col class="budget-col-progress" />
    </colgroup>
    <colgroup v-else-if="layout === 'pocket' && period === 'monthly'">
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-amount" />
        <col class="budget-col-progress" />
    </colgroup>
    <colgroup v-else>
        <col class="budget-col-label" />
        <col class="budget-col-plan" />
        <col class="budget-col-amount" />
        <col class="budget-col-forecast" />
        <col class="budget-col-progress" />
    </colgroup>
</template>

<style>
.budget-table {
    width: 100%;
    table-layout: fixed;
}

.budget-col-label {
    width: var(--budget-col-label, 11rem);
}

.budget-col-plan {
    width: var(--budget-col-plan, 9rem);
}

.budget-col-amount {
    width: var(--budget-col-amount, 8.5rem);
}

.budget-col-forecast {
    width: var(--budget-col-forecast, 8.5rem);
}

.budget-col-progress {
    width: var(--budget-col-progress, 4.5rem);
}
</style>
