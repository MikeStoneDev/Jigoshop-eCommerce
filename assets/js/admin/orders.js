jQuery(document).ready(function(t){var a;return a=function(a,r){var n;return n=jigoshop_admin_orders_list,t.ajax({url:n.ajax,type:"post",dataType:"json",data:{action:n.module,orderId:a,status:r}}).done(function(t){return t.success===!0?location.reload():alert(t.msg)}).fail(function(t){return alert(n.ajax_error)})},t(".btn-status").click(function(){return a(t(this).data("order_id"),t(this).data("status_to"))})});