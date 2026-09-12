import { fnolStatusMeta, type FnolStatus } from '../../api/fnol'

/**
 * FNOL status pill (open / converted / closed). Uses the design-system status
 * tokens so it renders correctly in light and dark mode. Shared by the FNOL
 * list + detail screens.
 */
export default function FnolStatusBadge({ status }: { status: FnolStatus }) {
  const meta = fnolStatusMeta(status)
  return (
    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${meta.cls}`}>
      {meta.label}
    </span>
  )
}
