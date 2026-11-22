<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

defineProps<{
  form: any
  meta: Record<string, any>
  action: 'update' | 'create'
}>()

const { t } = useI18n()
const { user } = useAuth()
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center mb-2">
      <div class="d-flex align-center">
        <VIcon
          class="me-2"
          icon="tabler-adjustments-horizontal"
          size="20"
        />
        <span>{{ t('reservation::frontend.policy') }}</span>
      </div>
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol
          v-if="action === 'create' && !user?.assigned_to_branch"
          cols="12"
          md="6"
        >
          <VSelect
            v-model="form.state.branch_id"
            :error="!!form.errors.value?.branch_id"
            :error-messages="form.errors.value?.branch_id"
            item-title="name"
            item-value="id"
            :items="meta.branches"
            :label="t('reservation::attributes.reservation_settings.branch_id')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.default_duration_minutes"
            v-integer-en
            :error="!!form.errors.value?.default_duration_minutes"
            :error-messages="form.errors.value?.default_duration_minutes"
            :label="t('reservation::attributes.reservation_settings.default_duration_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.min_duration_minutes"
            v-integer-en
            :error="!!form.errors.value?.min_duration_minutes"
            :error-messages="form.errors.value?.min_duration_minutes"
            :label="t('reservation::attributes.reservation_settings.min_duration_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.max_duration_minutes"
            v-integer-en
            :error="!!form.errors.value?.max_duration_minutes"
            :error-messages="form.errors.value?.max_duration_minutes"
            :label="t('reservation::attributes.reservation_settings.max_duration_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.slot_interval_minutes"
            v-integer-en
            :error="!!form.errors.value?.slot_interval_minutes"
            :error-messages="form.errors.value?.slot_interval_minutes"
            :label="t('reservation::attributes.reservation_settings.slot_interval_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.buffer_minutes"
            v-integer-en
            :error="!!form.errors.value?.buffer_minutes"
            :error-messages="form.errors.value?.buffer_minutes"
            :label="t('reservation::attributes.reservation_settings.buffer_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.min_guests"
            v-integer-en
            :error="!!form.errors.value?.min_guests"
            :error-messages="form.errors.value?.min_guests"
            :label="t('reservation::attributes.reservation_settings.min_guests')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.max_guests"
            v-integer-en
            :error="!!form.errors.value?.max_guests"
            :error-messages="form.errors.value?.max_guests"
            :label="t('reservation::attributes.reservation_settings.max_guests')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.large_party_threshold"
            v-integer-en
            :error="!!form.errors.value?.large_party_threshold"
            :error-messages="form.errors.value?.large_party_threshold"
            :label="t('reservation::attributes.reservation_settings.large_party_threshold')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.large_party_requires_approval"
            :label="t('reservation::attributes.reservation_settings.large_party_requires_approval')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.min_advance_minutes"
            v-integer-en
            :error="!!form.errors.value?.min_advance_minutes"
            :error-messages="form.errors.value?.min_advance_minutes"
            :label="t('reservation::attributes.reservation_settings.min_advance_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.max_advance_days"
            v-integer-en
            :error="!!form.errors.value?.max_advance_days"
            :error-messages="form.errors.value?.max_advance_days"
            :label="t('reservation::attributes.reservation_settings.max_advance_days')"
          />
        </VCol>

        <VCol cols="12">
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <VCheckbox
                v-model="form.state.reservations_enabled"
                :label="t('reservation::attributes.reservation_settings.reservations_enabled')"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VCheckbox
                v-model="form.state.allow_walk_in"
                :label="t('reservation::attributes.reservation_settings.allow_walk_in')"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VCheckbox
                v-model="form.state.pending_blocks_capacity"
                :label="t('reservation::attributes.reservation_settings.pending_blocks_capacity')"
              />
            </VCol>
          </VRow>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
