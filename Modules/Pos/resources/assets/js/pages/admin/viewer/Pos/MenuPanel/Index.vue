<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type {
  Category,
  PosForm,
  PosMeta,
  PosProductLoadOptions,
} from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'
import Categories from './Categories/Index.vue'
import Empty from './Empty.vue'
import Products from './Products/Index.vue'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
  loadProducts: (options?: PosProductLoadOptions) => Promise<void>
}>()

const { t } = useI18n()

const activeCategories = ref<Category[]>([])
const searchQuery = ref<string>('')

const selectedCategoryIds = computed(() => {
  const ids: Array<number | string> = []
  const collectIds = (category?: Category) => {
    if (!category) {
      return
    }

    if (!ids.includes(category.id)) {
      ids.push(category.id)
    }

    for (const item of category.items || []) {
      collectIds(item)
    }
  }

  collectIds(activeCategories.value[activeCategories.value.length - 1])

  return ids
})

const hasMoreProducts = computed(
  () =>
    !!props.meta.productsPagination &&
    props.meta.productsPagination.current_page < props.meta.productsPagination.last_page,
)

function onChangeRootCategory(category?: Category | null) {
  activeCategories.value = category ? [category] : []
}

async function loadMoreProducts() {
  if (!hasMoreProducts.value || props.form.loadingProducts || props.form.loadingMoreProducts) {
    return
  }

  await props.loadProducts({
    search: searchQuery.value.trim(),
    categoryIds: selectedCategoryIds.value,
    page: (props.meta.productsPagination?.current_page || 0) + 1,
  })
}

watch(
  () => props.form.menuId,
  () => {
    activeCategories.value = []
    searchQuery.value = ''
  },
)

watchDebounced(
  () => [searchQuery.value.trim(), selectedCategoryIds.value.join(',')],
  async () => {
    if (props.form.loadingMenuItems || !props.form.menuId) {
      return
    }

    await props.loadProducts({
      reset: true,
      search: searchQuery.value.trim(),
      categoryIds: selectedCategoryIds.value,
    })
  },
  { debounce: 250, maxWait: 600 },
)
</script>

<template>
  <div
    class="menu-items-container"
    :class="{
      'menu-items-container-center':
        meta.products.length === 0 &&
        meta.categories.length === 0 &&
        !form.loadingProducts &&
        !searchQuery,
    }"
  >
    <div
      v-if="form.loadingMenuItems && meta.products.length === 0"
      class="loading"
    >
      <VProgressCircular
        color="primary"
        indeterminate
        size="50"
      />
    </div>

    <template v-else>
      <template
        v-if="
          meta.categories.length > 0 ||
          meta.products.length > 0 ||
          form.loadingProducts ||
          searchQuery
        "
      >
        <div class="pos-product-search">
          <VTextField
            v-model="searchQuery"
            clearable
            hide-details
            prepend-inner-icon="tabler-search"
            :placeholder="t('pos::pos_viewer.search_products')"
            @click:clear="searchQuery = ''"
          >
            <template #prepend-inner>
              <VIcon icon="tabler-search" />
            </template>
          </VTextField>
        </div>

        <Categories
          :active-categories="activeCategories"
          :categories="meta.categories"
          @change-root="onChangeRootCategory"
        />

        <Products
          :cart="cart"
          :has-more="hasMoreProducts"
          :loading="form.loadingProducts"
          :loading-more="form.loadingMoreProducts"
          :products="meta.products"
          @load-more="loadMoreProducts"
        />
      </template>

      <Empty v-else />
    </template>
  </div>
</template>
