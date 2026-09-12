#!/usr/bin/env bash
#
# Removes the data dumps and legacy one-off scripts that are currently served
# from the Laravel web root (backend/public). Every file is git-tracked, so all
# of them remain recoverable from git history after this runs — this deletes
# them from the working tree only, it does not rewrite history.
#
# Run from the repo root:  bash scripts/purge-webroot-exposures.sh
#
set -euo pipefail

# Resolve the repo root from this script's own location, so the script works
# no matter which directory it is invoked from.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [ ! -d "$REPO_ROOT/backend/public" ]; then
  echo "ERROR: expected to find $REPO_ROOT/backend/public — is this script still in <repo>/scripts/?" >&2
  exit 1
fi
cd "$REPO_ROOT/backend"
echo "Working in: $PWD"
echo

removed=0
drop() {
  if [ -e "$1" ]; then
    git rm -q -f --ignore-unmatch -- "$1" 2>/dev/null || rm -f -- "$1"
    echo "  removed  $1"
    removed=$((removed + 1))
  fi
}

echo "== 1. Customer / payment data dumps served from the web root =="
drop "public/orange money listing 1-26.csv"          # 2,834 customers: name, phone, policy, amount
drop "public/uploads/file (10).csv"                  # ~390 customers, same schema
drop "public/uploads/Alpha+Direct+Alpha+Direct.csv"
drop "public/vcs/3385-VCS.csv"                       # 6,217 rows: name, email, amount
drop "public/vcs/output_success_dec22.csv"           # 20 MB, 107,719 payment records
drop "public/vcs/output_success_dec23.csv"
drop "public/vcs/output.csv"
drop "public/vcs/output1.csv"
drop "public/vcs/vcs_payments_to_be_imported.csv"
drop "public/vcs/vcs_payments_to_be_imported2.csv"
drop "public/vcs/vcs_payments_to_be_imported3.csv"
drop "public/pending-files/23-08-25-09-36-470.csv"
drop "public/pending-files/23-08-25-13-27-230.csv"   # bank: accountnumber, sortcode, accountholder
drop "public/pending-files/23-08-25-14-02-150.csv"   # bank: accountnumber, sortcode, accountholder
drop "public/pending-files/23-08-25-14-06-420.csv"
drop "public/orange/orange_mandate_json_log.sql"     # phpMyAdmin dump of payment mandates

echo "== 2. Executable scripts in the web root =="
drop "public/php_info.php"                           # phpinfo() -> dumps env, i.e. every SSM secret
drop "public/info/info.php"                          # phpinfo()
drop "public/test.php"                               # hardcoded RealPay gateway credentials
drop "public/accept.php"                             # unauthenticated raw-SQL writer -> orange_mandates
drop "public/orange/conn.php"                        # hardcoded DB credentials
drop "public/orange/api_fetch_all.php"               # unauthenticated full-table dump, CORS *
drop "public/orange/TestOrangeScheduleTransactionEvent.php"
drop "public/orange/EventEmitter.php"
drop "public/orange/OrangeMandeCodeConvert.php"
drop "public/vcs/index.php"                          # hardcoded MySQL root password
drop "public/vcs/VCS_data_restore.php"               # hardcoded MySQL root password
drop "public/vcs/vcs_data_update.php"
drop "public/vcs/vcs_failed_policy_update.php"
drop "public/whatsapp/index.php"                     # Infobip sample (placeholder token)

echo "== 3. Vendor test leftovers that reflect request input =="
drop "public/assets/vendors/general/jquery.repeater/test-post-parse.php"
drop "public/assets/vendors/general/jquery.repeater/test/echo.php"
drop "public/css/vendors/general/jquery.repeater/test-post-parse.php"
drop "public/css/vendors/general/jquery.repeater/test/echo.php"

echo "== 4. Host/infra info disclosure =="
drop "public/packages.txt"                           # dpkg listing -> reveals EOL Ubuntu 18.04 base

echo "== 5. Root-level one-off dev scripts =="
drop "_test_mis2022041218.php"                       # renders a real customer's statement PDF
drop "test_model_query.php"
drop "seed_policies.php"                             # writes real policies, commits (no rollback)
drop "generate_report_pdf.php"

echo
echo "Done. $removed file(s) removed from the working tree."
echo "All remain in git history — purge history separately if required (see report)."
