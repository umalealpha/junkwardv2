interface ImageReviewCardProps {
  title: string
  url: string | null
  status: number | null            // 1 = approve, 0 = reject, null = no decision yet
  remark: string
  onStatusChange: (status: number) => void
  onRemarkChange: (remark: string) => void
  disabled?: boolean
}

/**
 * A single inspection image with an Approve/Reject decision and a remark.
 * Shared by the vehicle and device pre-inspection edit screens.
 */
export default function ImageReviewCard({
  title, url, status, remark, onStatusChange, onRemarkChange, disabled,
}: ImageReviewCardProps) {
  return (
    <div className="border rounded-lg overflow-hidden bg-white flex flex-col">
      <div className="px-3 py-2 border-b bg-gray-50 flex items-center justify-between">
        <span className="text-sm font-medium text-gray-700">{title}</span>
        {status === 1 && <span className="text-xs font-medium text-green-700">Approved</span>}
        {status === 0 && <span className="text-xs font-medium text-red-700">Rejected</span>}
      </div>

      <div className="aspect-video bg-gray-100 flex items-center justify-center">
        {url ? (
          <a href={url} target="_blank" rel="noopener noreferrer" className="block w-full h-full">
            <img src={url} alt={title} className="w-full h-full object-cover hover:opacity-90 transition" />
          </a>
        ) : (
          <span className="text-xs text-gray-400">Not uploaded</span>
        )}
      </div>

      <div className="p-3 space-y-2">
        <div className="flex gap-4">
          <label className="flex items-center gap-1.5 text-sm cursor-pointer">
            <input
              type="radio"
              name={`status-${title}`}
              checked={status === 1}
              disabled={disabled}
              onChange={() => onStatusChange(1)}
              className="text-green-600 focus:ring-green-500"
            />
            <span className="text-green-700">Approve</span>
          </label>
          <label className="flex items-center gap-1.5 text-sm cursor-pointer">
            <input
              type="radio"
              name={`status-${title}`}
              checked={status === 0}
              disabled={disabled}
              onChange={() => onStatusChange(0)}
              className="text-red-600 focus:ring-red-500"
            />
            <span className="text-red-700">Reject</span>
          </label>
        </div>

        {status === 0 && (
          <textarea
            value={remark}
            disabled={disabled}
            onChange={e => onRemarkChange(e.target.value)}
            placeholder="Reason for rejection..."
            rows={2}
            className="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        )}
      </div>
    </div>
  )
}
