(() => {
  'use strict';
  const UI=window.TeacherDashboardUI, charts={}, $=(id)=>document.getElementById(id), n=(value)=>Number(value||0);
  function render(data){
    const cards=data.cards||{},tables=data.tables||{},graph=data.charts||{},classes=cards.classes||{},sections=cards.sections||{},due=cards.assessments_due||{},graded=cards.graded||{};
    $('stClasses').textContent=n(classes.total_classes);$('stClassSplit').textContent=`${n(classes.total_students)} learners · ${n(classes.average_class_size)} average size`;
    $('stSections').textContent=n(sections.total_sections);$('stSectionSplit').textContent=(sections.forms_taught||[]).join(', ')||'No sections assigned';
    $('stAssessments').textContent=n(due.pending_assessments);$('stAssessmentSplit').textContent=`${n(due.due_soon)} due soon · ${n(due.overdue)} overdue`;
    $('stGraded').textContent=n(graded.graded_this_week);$('stGradedSplit').textContent=`${n(graded.average_score).toFixed(1)} average score`;
    const total=n(graded.graded_this_week)+n(due.pending_assessments),rate=total?Math.round(n(graded.graded_this_week)/total*100):0;
    $('stMarkingRate').textContent=`${rate}%`;$('stMarkingRing').style.setProperty('--progress',rate);$('stMarkingCaption').textContent=`${n(due.pending_assessments)} assessments remain`;
    const pending=tables.pending_assessments?.data||tables.pending_assessments||[];
    $('stPendingBody').innerHTML=pending.length?pending.slice(0,7).map((row)=>`<tr><td><strong>${UI.escape(row.title)}</strong></td><td>${UI.escape(row.class)}</td><td>${UI.formatDate(row.due_date)}</td><td><span class="teacher-pill teacher-pill--warning">Pending</span></td></tr>`).join(''):'<tr><td colspan="4" class="teacher-empty">No pending assessments.</td></tr>';
    const schedule=tables.today_schedule||[];$('stScheduleList').innerHTML=schedule.length?schedule.map((row)=>UI.listItem('bi-clock',`${row.subject_name} · ${row.class_name}${row.stream_name?` ${row.stream_name}`:''}`,`${UI.formatTime(row.start_time)}–${UI.formatTime(row.end_time)} · ${row.room_name||'Room not set'}`)).join(''):'<p class="teacher-empty">No lessons scheduled today.</p>';
    $('stEventsList').innerHTML=UI.eventItems(tables.upcoming_events||[]);
    const exams=tables.exam_schedule?.data||tables.exam_schedule||[];$('stExamList').innerHTML=exams.length?exams.slice(0,5).map((row)=>UI.listItem('bi-journal-check',`Class ${row.class}`,`${UI.formatDate(row.date)} · ${UI.formatTime(row.time)}`,'rose')).join(''):'<p class="teacher-empty">No upcoming exams.</p>';
    charts.performance=UI.chart(charts.performance,$('subPerformanceChart'),{type:'bar',data:{labels:graph.subject_performance?.labels||[],datasets:[{data:graph.subject_performance?.data||[],backgroundColor:'#ef526c',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}});
    charts.trend=UI.chart(charts.trend,$('subGradingChart'),{type:'line',data:{labels:graph.assessment_trends?.labels||[],datasets:[{data:graph.assessment_trends?.data||[],borderColor:'#7559d9',backgroundColor:'rgba(117,89,217,.1)',fill:true,tension:.35}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
  }
  async function load(){const button=$('subjectTeacherDashboardRefresh');if(button)button.disabled=true;try{render(UI.unwrap(await window.API.dashboard.getSubjectTeacherFull()));const updated=$('subjectTeacherDashboardLastUpdated');if(updated)updated.textContent=new Intl.DateTimeFormat('en-GB',{hour:'2-digit',minute:'2-digit'}).format(new Date());}catch(error){window.showNotification?.(error.message||'Subject dashboard could not be loaded.','error');}finally{if(button)button.disabled=false;}}
  function init(){const root=$('subjectTeacherDashboard');if(!root)return;UI.setTeacherName(root);UI.bindRoutes(root);const refresh=$('subjectTeacherDashboardRefresh');if(refresh)refresh.addEventListener('click',load);load();}
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init,{once:true}):init();window.SubjectTeacherDashboardController={load};
})();
