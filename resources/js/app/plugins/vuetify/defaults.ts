export default {
  IconBtn: {
    icon: true,
    color: 'primary',
    variant: 'tonal',
    rounded: 'lg',
    ripple: true,
  },
  VAlert: {
    density: 'comfortable',
    variant: 'tonal',
    rounded: 'lg',
    elevation: 0,
    VBtn: {
      color: undefined,
      variant: 'text',
    },
  },
  VAvatar: {
    variant: 'flat',
  },
  VBadge: {
    color: 'primary',
    rounded: 'pill',
  },
  VBtn: {
    color: 'primary',
    variant: 'flat',
    rounded: 'lg',
    elevation: 0,
    ripple: true,
    class: 'app-btn',
  },

  VChip: {
    label: true,
    variant: 'tonal',
    rounded: 'lg',
    size: 'small',
  },
  VDataTable: {
    VPagination: {
      showFirstLastPage: true,
      firstIcon: 'tabler-chevrons-left',
      lastIcon: 'tabler-chevrons-right',
    },
  },
  VTable: {
    VCheckboxBtn: {
      density: 'comfortable',
    },
  },
  VDataTableServer: {
    VPagination: {
      showFirstLastPage: true,
      firstIcon: 'tabler-chevrons-left',
      lastIcon: 'tabler-chevrons-right',
    },
  },
  VList: {
    color: 'primary',
    density: 'compact',
    VCheckboxBtn: {
      density: 'compact',
    },
    VListItem: {
      ripple: false,
      VAvatar: {
        size: 38,
      },
    },
  },
  VMenu: {
    offset: '2px',
  },
  VPagination: {
    density: 'comfortable',
    variant: 'tonal',
  },
  VTabs: {
    color: 'primary',
    density: 'comfortable',
    VSlideGroup: {
      showArrows: true,
    },
  },
  VTooltip: {
    location: 'top',
  },
  VCheckboxBtn: {
    color: 'primary',
  },
  VCheckbox: {
    color: 'primary',
    density: 'comfortable',
    hideDetails: 'auto',
  },
  VRadioGroup: {
    color: 'primary',
    density: 'comfortable',
    hideDetails: 'auto',
  },
  VRadio: {
    density: 'comfortable',
    hideDetails: 'auto',
  },
  VSelect: {
    variant: 'outlined',
    color: 'primary',
    density: 'comfortable',
    hideDetails: 'auto',
    VChip: {
      label: true,
    },
  },
  VRangeSlider: {
    color: 'primary',
    trackSize: 6,
    thumbSize: 22,
    density: 'comfortable',
    thumbLabel: true,
    hideDetails: 'auto',
  },
  VRating: {
    activeColor: 'warning',
    color: 'disabled',
    emptyIcon: 'tabler-star-filled',
  },
  VProgressLinear: {
    height: 6,
    roundedBar: true,
    rounded: true,
    bgColor: 'rgba(var(--v-track-bg))',
    color: 'primary',
  },
  VSlider: {
    color: 'primary',
    thumbLabel: true,
    hideDetails: 'auto',
    thumbSize: 22,
    trackSize: 6,
    elevation: 4,
  },
  VTextField: {
    variant: 'outlined',
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto',
  },
  VAutocomplete: {
    variant: 'outlined',
    color: 'primary',
    density: 'comfortable',
    hideDetails: 'auto',
    menuProps: {
      contentClass: 'app-autocomplete__content v-autocomplete__content',
    },
    VChip: {
      label: true,
    },
  },
  VCombobox: {
    variant: 'outlined',
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto',
    VChip: {
      label: true,
    },
  },
  VFileInput: {
    variant: 'outlined',
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto',
    prependIcon: '',
    prependInnerIcon: 'tabler-link',
  },
  VTextarea: {
    variant: 'outlined',
    density: 'comfortable',
    color: 'primary',
    hideDetails: 'auto',
  },
  VSnackbar: {
    rounded: 'lg',
    elevation: 8,
    location: 'bottom right',
    VBtn: {
      size: 'small',
      variant: 'text',
    },
  },
  VSwitch: {
    inset: true,
    color: 'primary',
    hideDetails: 'auto',
    ripple: false,
  },
  VNavigationDrawer: {
    touchless: true,
  },
  VCard: {
    flat: true,
    rounded: 'lg',
  },
  VDialog: {
    rounded: 'xl',
    transition: 'dialog-bottom-transition',
    class: 'app-dialog',
  },
}
