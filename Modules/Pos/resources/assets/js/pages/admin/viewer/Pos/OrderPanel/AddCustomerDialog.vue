<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { getFormMeta, store } from '@/modules/user/api/customer.api.ts'

const dialogModel = defineModel<boolean>({ required: true })
const emit = defineEmits<{
  created: [customer: Record<string, unknown>]
}>()

const { t } = useI18n()

const countries = ref<Record<string, unknown>[]>([])

const form = useForm({
  name: '',
  phone_country_iso_code: '',
  phone: '',
  customer_type: 'regular',
  is_active: true,
})

async function loadMeta() {
  try {
    const response = (await getFormMeta()).data.body
    countries.value = response.countries || []
  } catch {
    countries.value = []
  }
}

watch(
  dialogModel,
  async (value) => {
    if (!value) {
      return
    }
    form.state.name = ''
    form.state.phone = ''
    form.state.customer_type = 'regular'
    form.state.is_active = true
    await loadMeta()
  },
  { immediate: true },
)

async function submit() {
  const response = await form.submit(() => store(form.state), true)
  if (response && response !== true) {
    emit('created', response.data.body)
    dialogModel.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="440"
    scrollable
  >
    <VCard class="pos-add-customer-dialog">
      <div class="pos-customer-dialog-header">
        <span class="pos-customer-dialog-icon">
          <VIcon
            icon="tabler-user-plus"
            size="22"
          />
        </span>
        <h2>{{ t('pos::pos_viewer.add_customer') }}</h2>
        <VBtn
          icon="tabler-x"
          variant="text"
          :aria-label="t('admin::admin.buttons.close')"
          @click="dialogModel = false"
        />
      </div>
      <VCardText class="pos-customer-dialog-fields">
        <VTextField
          v-model="form.state.name"
          class="pos-customer-name-field"
          :error="!!form.errors.value?.name"
          :error-messages="form.errors.value?.name"
          :label="t('user::attributes.users.name')"
          prepend-inner-icon="tabler-user"
          variant="outlined"
          hide-details="auto"
        />
        <VSelect
          v-model="form.state.phone_country_iso_code"
          :error="!!form.errors.value?.phone_country_iso_code"
          :error-messages="form.errors.value?.phone_country_iso_code"
          item-title="name"
          item-value="id"
          :items="countries"
          :label="t('user::attributes.users.phone_country_iso_code')"
          variant="outlined"
          hide-details="auto"
        />
        <VTextField
          v-model="form.state.phone"
          :error="!!form.errors.value?.phone"
          :error-messages="form.errors.value?.phone"
          :label="t('user::attributes.users.phone')"
          inputmode="tel"
          variant="outlined"
          hide-details="auto"
        />
      </VCardText>
      <VCardActions class="pos-customer-dialog-footer">
        <VBtn
          variant="outlined"
          @click="dialogModel = false"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :loading="form.loading.value"
          @click="submit"
        >
          {{ t('admin::admin.buttons.create') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
