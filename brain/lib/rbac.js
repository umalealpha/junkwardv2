'use strict';
/**
 * rbac.js — role → permission map + a `can(role, action)` helper.
 *
 * This is the SINGLE source of truth for who may do what. It is enforced
 * SERVER-SIDE on every /api route (server.js returns 403 when can() is false).
 * The dashboard also uses it to hide controls, but hiding is cosmetic only —
 * the server never trusts the client.
 *
 * Node built-ins only. No I/O here — pure data + pure functions, so it is
 * trivially unit-testable (see test/rbac.test.js).
 *
 * Permission vocabulary (actions):
 *   view.overview     — the overview / activity dashboard
 *   view.activity     — the read-only audit / activity log
 *   view.queue        — the full ranked exception queue (all teams)
 *   view.queue.claims — the queue, but only the Claims team slice
 *   view.affected     — the affected-policies table (collections / arrears)
 *   approve           — record a human approval on a queued item
 *   export            — export a list (e.g. affected policies as CSV)
 *   admin.users       — create / read / update / delete dashboard users
 *   admin.settings    — read / edit settings (webhook, recipients, toggles)
 */

const ROLES = ['admin', 'finance', 'underwriting', 'claims', 'viewer'];

// Every action the system knows about. `can()` throws on anything not here so
// a typo in a route guard fails loud in tests rather than silently allowing.
const ACTIONS = [
  'view.overview', 'view.activity', 'view.queue', 'view.queue.claims',
  'view.affected', 'approve', 'export', 'admin.users', 'admin.settings',
];

// admin is handled specially (gets everything) so it never drifts out of sync
// as new actions are added. The other roles are explicit allow-lists.
const PERMISSIONS = {
  admin: new Set(ACTIONS),
  finance: new Set([
    'view.overview', 'view.activity', 'view.queue', 'view.affected',
    'approve', 'export',
  ]),
  underwriting: new Set([
    'view.overview', 'view.activity', 'view.queue', 'view.affected',
    'approve',
  ]),
  // Claims sees only claims-related queue items + the activity log.
  claims: new Set([
    'view.overview', 'view.activity', 'view.queue.claims',
  ]),
  // Read-only everything, no state-changing actions and no export.
  viewer: new Set([
    'view.overview', 'view.activity', 'view.queue', 'view.affected',
  ]),
};

function isRole(role) { return ROLES.includes(role); }

/**
 * can(role, action) -> boolean
 *   True when `role` is permitted `action`. Unknown role → false (fail-closed).
 *   Unknown action → throws (programmer error; must be caught by tests).
 */
function can(role, action) {
  if (!ACTIONS.includes(action)) throw new Error(`unknown action: ${action}`);
  const set = PERMISSIONS[role];
  if (!set) return false;
  return set.has(action);
}

// The flat list of permissions a role holds — handy for GET /api/me so the UI
// can hide controls the user can't use.
function permissionsFor(role) {
  const set = PERMISSIONS[role];
  return set ? Array.from(set).sort() : [];
}

module.exports = { ROLES, ACTIONS, PERMISSIONS, can, isRole, permissionsFor };
