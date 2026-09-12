import { lazy, Suspense } from 'react'
import { useClaimsFnolEnabled } from '../../hooks/useFnol'
import LoadingSpinner from '../../components/common/LoadingSpinner'

/**
 * ClaimCreateGate — the flag-gated "/claims/create" entry point.
 *
 * This makes the tracker-style FNOL create form the SINGLE claim-creation path
 * without deleting the legacy create page:
 *   - `claims_fnol` flag ON  → renders the unified tracker-style FnolCreatePage.
 *   - `claims_fnol` flag OFF → renders the legacy ClaimCreatePage, i.e. behaviour
 *     is UNCHANGED from before this change (the current default).
 *
 * Both pages stay lazy-loaded so the OFF path ships exactly the same bundle it
 * did before. The flag default is NOT changed here — flipping it is a separate
 * ops / claims-domain decision, and this gate makes that flip a one-switch,
 * fully reversible cutover.
 */
const ClaimCreatePage = lazy(() => import('./ClaimCreatePage'))
const FnolCreatePage = lazy(() => import('./FnolCreatePage'))

export default function ClaimCreateGate() {
  const fnolOn = useClaimsFnolEnabled()
  return (
    <Suspense fallback={<LoadingSpinner />}>
      {fnolOn ? <FnolCreatePage /> : <ClaimCreatePage />}
    </Suspense>
  )
}
