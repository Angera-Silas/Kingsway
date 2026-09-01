(() => {
  'use strict';
  const UI = window.TeacherDashboardUI, charts = {};
  const $ = (id) => document.getElementById(id), n = (value) => Number(value || 0);
  function schedule(rows) {
    $('ctScheduleList').innerHTML = rows?.length ? rows.map((row) => UI.listItem('bi-clock', row.subject || row.subject_name, `${UI.formatTime(row.start_time || row.time)} · ${row.teacher || row.room_name || 'Class lesson'}`)).join('') : '<p class="teacher-empty">No lessons scheduled today.</p>';
    const completed = rows?.filter((row) => row.status === 'completed' || (row.end_time && row.end_time < new Date().toTimeString().slice(0, 8))).length || 0;
    const rate = rows?.length ? Math.round(completed / rows.length * 100) : 0;
    $('ctWorkRate').textContent = `${rate}%`; $('ctWorkRing').style.setProperty('--progress', rate);
    const next = rows?.find((row) => !row.end_time || row.end_time >= new Date().toTimeString().slice(0, 8));
    $('ctNextLesson').textContent = next ? `Next: ${next.subject || next.subject_name} at ${UI.formatTime(next.start_time || next.time)}` : 'Teaching schedule complete';
  }
  function render(data) {
    const cards = data.cards || {}, tables = data.tables || {}, graph = data.charts || {};
    const students = cards.my_students || {}, attendance = cards.today_attendance || {}, performance = cards.class_performance || {}, assessments = cards.pending_assessments || {};
    $('ctClassLabel').textContent = students.class_name && students.class_name !== 'Not Assigned' ? `Class teacher · ${students.class_name}` : 'No active class assignment';
    $('ctStudents').textContent = n(students.total).toLocaleString(); $('ctGenderSplit').textContent = `${n(students.male)} boys · ${n(students.female)} girls`;
    $('ctAttendance').textContent = `${n(attendance.percentage)}%`; $('ctAttendanceSplit').textContent = `${n(attendance.present)} present · ${n(attendance.absent)} absent · ${n(attendance.late)} late`;
    $('ctAverage').textContent = `${n(performance.average_score).toFixed(1)}%`; $('ctPerformanceSplit').textContent = `${n(performance.high_performers)} excelling · ${n(performance.needs_support)} need support`;
    $('ctAssessments').textContent = n(assessments.pending).toLocaleString(); $('ctAssessmentSplit').textContent = `${n(assessments.overdue)} overdue`;
    const roster = tables.student_assessment_status || [];
    $('ctStudentBody').innerHTML = roster.length ? roster.slice(0, 7).map((row) => `<tr><td><strong>${UI.escape(row.student_name)}</strong></td><td>${UI.escape(row.admission_no)}</td><td>${n(row.average_score).toFixed(1)}%</td><td>${n(row.assessments_taken)}</td><td><span class="teacher-pill">${UI.escape(row.status)}</span></td></tr>`).join('') : '<tr><td colspan="5" class="teacher-empty">No learner performance records.</td></tr>';
    schedule(tables.today_schedule || []); $('ctEventsList').innerHTML = UI.eventItems(tables.upcoming_events || []);
    charts.attendance = UI.chart(charts.attendance, $('ctAttendanceChart'), { type:'line', data:{ labels:graph.attendance_trend?.labels || [], datasets:[{ label:'Attendance %', data:graph.attendance_trend?.data || [], borderColor:'#16a36a', backgroundColor:'rgba(22,163,106,.12)', fill:true, tension:.35 }] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}} });
    charts.performance = UI.chart(charts.performance, $('ctPerformanceChart'), { type:'bar', data:{ labels:graph.assessment_performance?.labels || [], datasets:[{ data:graph.assessment_performance?.data || [], backgroundColor:['#16a36a','#57bca0','#f1be57','#ee7d68'],borderRadius:6 }] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}} });
  }
  async function load() { const button=$('classTeacherDashboardRefresh'); if(button) button.disabled=true; try { render(UI.unwrap(await window.API.dashboard.getClassTeacherFull())); const updated=$('classTeacherDashboardLastUpdated'); if(updated) updated.textContent=new Intl.DateTimeFormat('en-GB',{hour:'2-digit',minute:'2-digit'}).format(new Date()); } catch(error){ window.showNotification?.(error.message || 'Class dashboard could not be loaded.','error'); } finally { if(button) button.disabled=false; } }
  function init(){const root=$('classTeacherDashboard');if(!root)return;UI.setTeacherName(root);UI.bindRoutes(root);const refresh=$('classTeacherDashboardRefresh');if(refresh)refresh.addEventListener('click',load);load();}
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init,{once:true}):init();
  window.ClassTeacherDashboardController={load};
})();
