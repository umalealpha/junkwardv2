import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchProducts, setProductVisibility, type Product } from '../../api/products'

export default function ProductListPage() {
  const qc = useQueryClient()
  const products = useQuery({ queryKey: ['products'], queryFn: fetchProducts })

  const toggle = useMutation({
    mutationFn: ({ id, isForStart }: { id: number; isForStart: boolean }) =>
      setProductVisibility(id, isForStart),
    onMutate: async ({ id, isForStart }) => {
      await qc.cancelQueries({ queryKey: ['products'] })
      const prev = qc.getQueryData<Product[]>(['products'])
      qc.setQueryData<Product[]>(['products'], (old) =>
        old?.map((p) => (p.id === id ? { ...p, isForStart } : p)) ?? old,
      )
      return { prev }
    },
    onError: (_err, _vars, ctx) => {
      if (ctx?.prev) qc.setQueryData(['products'], ctx.prev)
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['products'] })
    },
  })

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Products</h1>
          <p className="text-sm text-gray-500 mt-1">
            Toggle <span className="font-semibold">Show on start site</span> to control which products appear on{' '}
            <a href="https://start.alphadirect.co.bw" className="text-orange-600 hover:underline" target="_blank" rel="noreferrer">
              start.alphadirect.co.bw
            </a>
            .
          </p>
        </div>
      </div>

      <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
        {products.isLoading && <div className="p-8 text-center text-gray-400">Loading…</div>}
        {products.isError && (
          <div className="p-8 text-center text-rose-600">
            Failed to load products. {(products.error as Error)?.message ?? ''}
          </div>
        )}
        {products.data && (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
              <tr>
                <th className="text-left  px-4 py-3 w-12">ID</th>
                <th className="text-left  px-4 py-3">Name</th>
                <th className="text-left  px-4 py-3 hidden md:table-cell">Type</th>
                <th className="text-left  px-4 py-3 hidden lg:table-cell">Flags</th>
                <th className="text-left  px-4 py-3">Status</th>
                <th className="text-right px-4 py-3">Show on start site</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {products.data.map((p) => (
                <tr key={p.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-500">{p.id}</td>
                  <td className="px-4 py-3 font-medium text-gray-900">{p.name}</td>
                  <td className="px-4 py-3 text-gray-600 hidden md:table-cell">{p.type ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600 hidden lg:table-cell">
                    {[p.hasVehicle && 'vehicle', p.hasMember && 'member'].filter(Boolean).join(', ') || '—'}
                  </td>
                  <td className="px-4 py-3">
                    {p.status === 1 ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 px-2 py-0.5 text-xs font-medium">
                        Active
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-600 ring-1 ring-gray-200 px-2 py-0.5 text-xs font-medium">
                        Inactive
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Toggle
                      checked={!!p.isForStart}
                      disabled={toggle.isPending && toggle.variables?.id === p.id}
                      onChange={(checked) => toggle.mutate({ id: p.id, isForStart: checked })}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  )
}

function Toggle({
  checked,
  onChange,
  disabled,
}: {
  checked: boolean
  onChange: (v: boolean) => void
  disabled?: boolean
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
        checked ? 'bg-orange-500' : 'bg-gray-300'
      }`}
    >
      <span
        className={`inline-block size-5 transform rounded-full bg-white shadow transition-transform ${
          checked ? 'translate-x-5' : 'translate-x-0.5'
        }`}
      />
    </button>
  )
}
