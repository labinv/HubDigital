#!/usr/bin/env bash
set -euo pipefail

# Mide la VM real sin sumar RSS como memoria total: MemoryCurrent por cgroup y
# MemAvailable son las medidas principales. Un servicio no activo se registra
# como NA, nunca como un cero que parezca consumo válido.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la medición con sudo.' >&2
    exit 64
fi

label="${1:-}"
requested_seconds="${2:-60}"
[[ "${label}" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'Etiqueta inválida.' >&2; exit 64; }
[[ "${requested_seconds}" =~ ^[0-9]+$ && "${requested_seconds}" -ge 10 && "${requested_seconds}" -le 1800 ]] || {
    echo 'Duración entre 10 y 1800 segundos.' >&2
    exit 64
}

output_dir=/var/lib/hubdigital/measurements
install -d -m 0700 "${output_dir}"
started_epoch="$(date +%s)"
started_utc="$(date --utc +%FT%TZ)"
output="${output_dir}/$(date --utc +%Y%m%dT%H%M%SZ)-${label}.csv"
units=(postgresql@16-main.service php8.4-fpm.service hubdigital-worker.service hubdigital-schedule.service nginx.service cloudflared-hubdigital.service)

unit_active() {
    systemctl is-active --quiet "$1"
}
unit_value() {
    local unit="$1" property="$2" value
    if ! unit_active "${unit}"; then
        printf 'NA'
        return
    fi
    value="$(systemctl show --property="${property}" --value "${unit}" 2>/dev/null || true)"
    [[ "${value}" =~ ^[0-9]+$ ]] && printf '%s' "${value}" || printf 'NA'
}
unit_counter() {
    local unit="$1" property="$2" value
    value="$(systemctl show --property="${property}" --value "${unit}" 2>/dev/null || true)"
    [[ "${value}" =~ ^[0-9]+$ ]] && printf '%s' "${value}" || printf 'NA'
}
unit_state() {
    local value
    value="$(systemctl is-active "$1" 2>/dev/null || true)"
    [[ -n "${value}" ]] && printf '%s' "${value}" || printf 'unknown'
}
unit_rss_kib() {
    local unit="$1" control_group pids
    if ! unit_active "${unit}"; then
        printf 'NA'
        return
    fi
    control_group="$(systemctl show --property=ControlGroup --value "${unit}" 2>/dev/null || true)"
    [[ -n "${control_group}" && -r "/sys/fs/cgroup${control_group}/cgroup.procs" ]] || { printf 'NA'; return; }
    pids="$(tr '\n' ' ' < "/sys/fs/cgroup${control_group}/cgroup.procs")"
    [[ -n "${pids// }" ]] || { printf '0'; return; }
    ps -o rss= -p ${pids} 2>/dev/null | awk '{ total += $1 } END { print total + 0 }'
}
hubdigital_tmpfs_kib() {
    local value
    [[ -d /run/hubdigital ]] || { printf 'NA'; return; }
    value="$(du -sk -- /run/hubdigital 2>/dev/null | awk 'NR == 1 { print $1 }')"
    [[ "${value}" =~ ^[0-9]+$ ]] && printf '%s' "${value}" || printf 'NA'
}
run_tmpfs_value_kib() {
    local column="$1" value
    value="$(df -Pk /run 2>/dev/null | awk -v column="${column}" 'NR == 2 && $column ~ /^[0-9]+$/ { print $column }')"
    [[ "${value}" =~ ^[0-9]+$ ]] && printf '%s' "${value}" || printf 'NA'
}
cpu_stat() {
    # guest y guest_nice ya están contenidos en user/nice: no se suman otra vez.
    awk '/^cpu / { total = 0; for (i = 2; i <= 9; i++) total += $i; print total, $5 + $6; exit }' /proc/stat
}

printf 'timestamp_utc,mem_available_kib,mem_total_kib,swap_used_kib,load1,system_cpu_percent,postgres_state,php_fpm_state,worker_state,scheduler_state,nginx_state,cloudflared_state,postgres_cgroup_bytes,php_fpm_cgroup_bytes,worker_cgroup_bytes,scheduler_cgroup_bytes,nginx_cgroup_bytes,cloudflared_cgroup_bytes,postgres_rss_kib,php_fpm_rss_kib,worker_rss_kib,scheduler_rss_kib,nginx_rss_kib,cloudflared_rss_kib,hubdigital_tmpfs_kib,run_tmpfs_used_kib,run_tmpfs_available_kib\n' > "${output}"
declare -A cpu_start restart_start
for unit in "${units[@]}"; do
    cpu_start["${unit}"]="$(unit_counter "${unit}" CPUUsageNSec)"
    restart_start["${unit}"]="$(unit_counter "${unit}" NRestarts)"
done
read -r previous_cpu_total previous_cpu_idle < <(cpu_stat)
deadline_epoch=$((started_epoch + requested_seconds))

while (( $(date +%s) < deadline_epoch )); do
    mem_available="$(awk '/MemAvailable:/ {print $2}' /proc/meminfo)"
    mem_total="$(awk '/MemTotal:/ {print $2}' /proc/meminfo)"
    swap_total="$(awk '/SwapTotal:/ {print $2}' /proc/meminfo)"
    swap_free="$(awk '/SwapFree:/ {print $2}' /proc/meminfo)"
    swap_used=$((swap_total - swap_free))
    load1="$(awk '{print $1}' /proc/loadavg)"
    read -r current_cpu_total current_cpu_idle < <(cpu_stat)
    cpu_delta=$((current_cpu_total - previous_cpu_total))
    idle_delta=$((current_cpu_idle - previous_cpu_idle))
    if [[ "${cpu_delta}" -gt 0 ]]; then
        system_cpu_percent="$(awk -v total="${cpu_delta}" -v idle="${idle_delta}" 'BEGIN { printf "%.2f", 100 * (total - idle) / total }')"
    else
        system_cpu_percent=0
    fi
    previous_cpu_total="${current_cpu_total}"
    previous_cpu_idle="${current_cpu_idle}"

    printf '%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
        "$(date --utc +%FT%TZ)" "${mem_available}" "${mem_total}" "${swap_used}" "${load1}" "${system_cpu_percent}" \
        "$(unit_state postgresql@16-main.service)" "$(unit_state php8.4-fpm.service)" \
        "$(unit_state hubdigital-worker.service)" "$(unit_state hubdigital-schedule.service)" \
        "$(unit_state nginx.service)" "$(unit_state cloudflared-hubdigital.service)" \
        "$(unit_value postgresql@16-main.service MemoryCurrent)" "$(unit_value php8.4-fpm.service MemoryCurrent)" \
        "$(unit_value hubdigital-worker.service MemoryCurrent)" "$(unit_value hubdigital-schedule.service MemoryCurrent)" \
        "$(unit_value nginx.service MemoryCurrent)" "$(unit_value cloudflared-hubdigital.service MemoryCurrent)" \
        "$(unit_rss_kib postgresql@16-main.service)" "$(unit_rss_kib php8.4-fpm.service)" \
        "$(unit_rss_kib hubdigital-worker.service)" "$(unit_rss_kib hubdigital-schedule.service)" \
        "$(unit_rss_kib nginx.service)" "$(unit_rss_kib cloudflared-hubdigital.service)" \
        "$(hubdigital_tmpfs_kib)" "$(run_tmpfs_value_kib 3)" "$(run_tmpfs_value_kib 4)" \
        >> "${output}"
    sleep 1
done

finished_epoch="$(date +%s)"
summary="${output%.csv}.summary"
{
    echo "label=${label}"
    echo "started_utc=${started_utc}"
    echo "requested_seconds=${requested_seconds}"
    echo "actual_seconds=$((finished_epoch - started_epoch))"
    echo 'cpu_usage_nsec_delta_by_unit:'
    for unit in "${units[@]}"; do
        cpu_end="$(unit_counter "${unit}" CPUUsageNSec)"
        cpu_begin="${cpu_start["${unit}"]}"
        if [[ "${cpu_end}" =~ ^[0-9]+$ && "${cpu_begin}" =~ ^[0-9]+$ ]]; then
            printf '%s=%s\n' "${unit}" "$((cpu_end - cpu_begin))"
        else
            printf '%s=NA\n' "${unit}"
        fi
    done
    echo 'restart_delta_by_unit:'
    for unit in "${units[@]}"; do
        restart_end="$(unit_counter "${unit}" NRestarts)"
        restart_begin="${restart_start["${unit}"]}"
        if [[ "${restart_end}" =~ ^[0-9]+$ && "${restart_begin}" =~ ^[0-9]+$ ]]; then
            printf '%s=%s\n' "${unit}" "$((restart_end - restart_begin))"
        else
            printf '%s=NA\n' "${unit}"
        fi
    done
    echo 'peaks_and_minima:'
    awk -F, '
        NR == 2 { min_mem = $2 }
        NR > 1 {
            if ($2 < min_mem) min_mem = $2
            if ($4 > max_swap) max_swap = $4
            if ($5 > max_load) max_load = $5
            if ($6 > max_system_cpu) max_system_cpu = $6
            for (i = 13; i <= 24; i++) {
                if ($i ~ /^[0-9]+$/ && (!seen[i] || $i > max[i])) max[i] = $i
                if ($i ~ /^[0-9]+$/) seen[i] = 1
            }
            if ($25 ~ /^[0-9]+$/ && (!tmpfs_seen || $25 > max_tmpfs)) max_tmpfs = $25
            if ($25 ~ /^[0-9]+$/) tmpfs_seen = 1
            if ($26 ~ /^[0-9]+$/ && (!run_used_seen || $26 > max_run_used)) max_run_used = $26
            if ($26 ~ /^[0-9]+$/) run_used_seen = 1
            if ($27 ~ /^[0-9]+$/ && (!run_available_seen || $27 < min_run_available)) min_run_available = $27
            if ($27 ~ /^[0-9]+$/) run_available_seen = 1
        }
        END {
            printf "min_mem_available_kib=%s\n", min_mem + 0
            printf "max_swap_used_kib=%s\n", max_swap + 0
            printf "max_load1=%s\n", max_load + 0
            printf "max_system_cpu_percent=%s\n", max_system_cpu + 0
            split("postgres_cgroup_bytes php_fpm_cgroup_bytes worker_cgroup_bytes scheduler_cgroup_bytes nginx_cgroup_bytes cloudflared_cgroup_bytes postgres_rss_kib php_fpm_rss_kib worker_rss_kib scheduler_rss_kib nginx_rss_kib cloudflared_rss_kib", names, " ")
            for (i = 1; i <= 12; i++) printf "max_%s=%s\n", names[i], (seen[i + 12] ? max[i + 12] : "NA")
            printf "max_hubdigital_tmpfs_kib=%s\n", (tmpfs_seen ? max_tmpfs : "NA")
            printf "max_run_tmpfs_used_kib=%s\n", (run_used_seen ? max_run_used : "NA")
            printf "min_run_tmpfs_available_kib=%s\n", (run_available_seen ? min_run_available : "NA")
        }
    ' "${output}"
    echo 'oom_or_cgroup_events:'
    journalctl --since "${started_utc}" --no-pager | grep -Ei 'out of memory|oom-kill|killed process|memory cgroup|memory.max' || true
    echo 'service_status:'
    systemctl --no-pager --full status "${units[@]}" || true
} > "${summary}"
chmod 0600 "${output}" "${summary}"
printf '%s\n' "${output}"
