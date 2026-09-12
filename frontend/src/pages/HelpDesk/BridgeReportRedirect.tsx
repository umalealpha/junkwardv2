import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { openAlphaBridgeWidget } from '../../utils/alphaBridge'
import { useToast } from '../../components/common/Toast'

/**
 * /help-desk/new — ticket creation has moved to the Alpha Bridge widget.
 *
 * This route used to render Graphite's own submit form (HelpDeskSubmitPage,
 * kept on disk). Deep links and bookmarks to /help-desk/new still work: we
 * land the user on the dashboard and pop the Bridge report dialog over it.
 * (NOT /help-desk — since the 2026-07-14 module retirement that URL forwards
 * to the Bridge board, which would tear down the widget dialog.)
 */
export default function BridgeReportRedirect() {
  const navigate = useNavigate()
  const { toast } = useToast()

  useEffect(() => {
    navigate('/', { replace: true })
    void openAlphaBridgeWidget().catch(() =>
      toast.error('Could not open the issue reporter — Alpha Bridge is unreachable. Please try again.')
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return null
}
