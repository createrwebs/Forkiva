export interface WidgetCustomizerDefinition {
  key: string
  category: string
  kind: string
  icon: string
  color: string
}

export interface WidgetCustomizerLayoutItem {
  key: string
  visible: boolean
  minimized: boolean
  order: number
}

export interface WidgetCustomizerLabels {
  title: string
  subtitle: string
  search: string
  visible: string
  hidden: string
  dragHint: string
  empty: string
  reset: string
  done: string
  minimize: string
  expand: string
}
