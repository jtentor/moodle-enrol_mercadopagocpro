<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cadenas del método de inscripción Mercado Pago Checkout Pro.
 *
 * @package    enrol_mpcheckoutpro
 * @copyright  2026 Julio Tentor <jtentor@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mercado Pago Checkout Pro';
$string['pluginname_desc'] = 'El método de inscripción Mercado Pago Checkout Pro permite que los estudiantes paguen un curso a través de Mercado Pago y queden inscriptos automáticamente cuando se acredita el pago.';

// Capacidades.
$string['mpcheckoutpro:config'] = 'Configurar instancias de inscripción Mercado Pago Checkout Pro';
$string['mpcheckoutpro:manage'] = 'Gestionar usuarios inscriptos';
$string['mpcheckoutpro:unenrol'] = 'Desinscribir usuarios del curso';
$string['mpcheckoutpro:unenrolself'] = 'Desinscribirse del curso';
$string['mpcheckoutpro:viewtransactions'] = 'Ver las transacciones de pago de Mercado Pago';
$string['mpcheckoutpro:reconcile'] = 'Reconsultar un pago contra la API de Mercado Pago';

// Grupos de ajustes.
$string['settings_credentials'] = 'Credenciales de Mercado Pago';
$string['settings_credentials_desc'] = 'Las credenciales se obtienen en <em>Tus integraciones</em>, en el panel de desarrolladores de Mercado Pago. También pueden definirse en config.php como <code>$CFG->enrol_mpcheckoutpro</code> o mediante las variables de entorno <code>MPCHECKOUTPRO_ACCESS_TOKEN</code>, <code>MPCHECKOUTPRO_PUBLIC_KEY</code> y <code>MPCHECKOUTPRO_WEBHOOK_SECRET</code>, que tienen prioridad sobre los valores guardados aquí.';
$string['settings_webhooks'] = 'Webhooks';
$string['settings_webhooks_desc'] = 'Mercado Pago firma cada notificación con la clave secreta de tu aplicación. Mantené activada la validación de firma en los sitios en producción.';
$string['settings_preference'] = 'Preferencia de Checkout Pro';
$string['settings_preference_desc'] = 'Valores por defecto aplicados a cada preferencia de pago creada por este plugin. Cada curso puede sobrescribir algunos de ellos.';
$string['settings_marketplace'] = 'Split payments (marketplace)';
$string['settings_marketplace_desc'] = 'Split payments permite que un marketplace cobre una comisión sobre cada venta. Registrá <code>{$a->redirecturi}</code> como URI de redirección de tu aplicación de Mercado Pago antes de conectar cualquier vendedor.';
$string['settings_behaviour'] = 'Comportamiento de la inscripción';
$string['settings_diagnostics'] = 'Diagnóstico y rendimiento';

// Credenciales.
$string['environment'] = 'Entorno';
$string['environment_desc'] = 'Qué juego de credenciales usar. En el entorno de prueba el comprador va al checkout de sandbox y no se mueve dinero real.';
$string['environment_production'] = 'Producción';
$string['environment_test'] = 'Prueba';
$string['accesstoken'] = 'Access token';
$string['accesstoken_desc'] = 'Access token de producción de tu aplicación de Mercado Pago. Se usa como Bearer token en todas las llamadas a la API.';
$string['publickey'] = 'Public key';
$string['publickey_desc'] = 'Public key de producción de tu aplicación de Mercado Pago.';
$string['webhooksecret'] = 'Clave secreta de webhooks';
$string['webhooksecret_desc'] = 'La clave secreta que aparece junto a la configuración de webhooks en <em>Tus integraciones</em>. Sin ella no se pueden verificar las notificaciones entrantes.';
$string['testaccesstoken'] = 'Access token de prueba';
$string['testaccesstoken_desc'] = 'Access token de tus credenciales de prueba, usado cuando el entorno es Prueba.';
$string['testpublickey'] = 'Public key de prueba';
$string['testwebhooksecret'] = 'Clave secreta de webhooks de prueba';
$string['allowinstancecredentials'] = 'Permitir credenciales por curso';
$string['allowinstancecredentials_desc'] = 'Permite que cada instancia de inscripción guarde sus propias credenciales de Mercado Pago. Es útil cuando distintas áreas cobran en cuentas distintas. Las credenciales de instancia se cifran y nunca se incluyen en las copias de seguridad de cursos.';
$string['instancecredentials'] = 'Credenciales para este curso';
$string['instancecredentials_desc'] = 'Dejá estos campos vacíos para usar las credenciales del sitio. Lo que se ingrese aquí se cifra antes de guardarse y nunca se exporta en una copia de seguridad del curso.';
$string['keepcredentials'] = 'Conservar las credenciales guardadas';
$string['keepcredentials_help'] = 'Esta instancia ya tiene credenciales propias. Dejá esta casilla marcada para conservarlas; desmarcala y guardá con los campos vacíos para borrarlas y volver a las credenciales del sitio.';
$string['sdkversion'] = 'SDK PHP de Mercado Pago versión {$a} detectado.';
$string['webhookurl_desc'] = 'Configurá esta URL como endpoint de webhooks de tu aplicación de Mercado Pago: <code>{$a}</code>';

// Webhooks.
$string['requiresignature'] = 'Exigir firma válida';
$string['requiresignature_desc'] = 'Rechaza toda notificación cuyo encabezado <code>x-signature</code> no pueda verificarse. Desactivalo solamente para depurar.';
$string['signaturetolerance'] = 'Tolerancia de la marca de tiempo de la firma';
$string['signaturetolerance_desc'] = 'Diferencia máxima aceptada, en segundos, entre la marca de tiempo de la firma y el reloj del servidor. 0 omite la verificación.';
$string['deferwebhooks'] = 'Procesar las notificaciones en segundo plano';
$string['deferwebhooks_desc'] = 'Confirma cada notificación de inmediato y hace la llamada a la API de Mercado Pago en la tarea programada. Usalo si tu servidor no puede responder de forma confiable dentro de los cinco segundos que da Mercado Pago.';
$string['webhookratelimit'] = 'Límite de notificaciones';
$string['webhookratelimit_desc'] = 'Máximo de notificaciones aceptadas por minuto y por dirección remota. 0 desactiva el límite.';
$string['checkoutratelimit'] = 'Límite de checkouts';
$string['checkoutratelimit_desc'] = 'Máximo de preferencias de pago que un mismo usuario puede crear por minuto. 0 desactiva el límite.';
$string['reconcilemaxattempts'] = 'Máximo de reintentos de conciliación';
$string['reconcilemaxattempts_desc'] = 'Deja de reconsultar una transacción contra la API después de esta cantidad de intentos.';
$string['reconcilemaxage'] = 'Antigüedad máxima para conciliar';
$string['reconcilemaxage_desc'] = 'Deja de reconsultar transacciones más antiguas que este período.';

// Preferencia.
$string['autoreturn'] = 'Volver automáticamente';
$string['autoreturn_desc'] = 'Envía <code>auto_return=approved</code> para que el comprador vuelva a Moodle automáticamente después de un pago aprobado.';
$string['binarymode'] = 'Modo binario';
$string['binarymode_desc'] = 'Cuando está activo, un pago solo puede quedar aprobado o rechazado, nunca pendiente. Reduce la tasa de aprobación, así que dejalo desactivado salvo que lo necesites.';
$string['statementdescriptor'] = 'Descriptor del resumen';
$string['statementdescriptor_desc'] = 'Texto corto que aparece en el resumen de la tarjeta del comprador. Solo letras, números y espacios, hasta 22 caracteres.';
$string['preferenceexpiry'] = 'Validez de la preferencia';
$string['preferenceexpiry_desc'] = 'Cuánto tiempo sigue siendo válido el enlace de pago. 0 no envía fechas de expiración.';
$string['installments'] = 'Cuotas máximas';
$string['installments_desc'] = 'Cantidad máxima de cuotas ofrecidas al comprador. 0 deja que Mercado Pago decida.';
$string['installments_help'] = 'Corresponde a <code>payment_methods.installments</code> en la preferencia. Poné 0 para que este curso use el valor del sitio.';
$string['defaultinstallments'] = 'Cuotas preseleccionadas';
$string['defaultinstallments_desc'] = 'Cantidad de cuotas preseleccionada en el checkout. 0 no preselecciona nada.';
$string['defaultinstallments_help'] = 'Corresponde a <code>payment_methods.default_installments</code>. Nunca puede superar la cantidad máxima de cuotas.';
$string['excludedpaymenttypes'] = 'Tipos de pago excluidos';
$string['excludedpaymenttypes_desc'] = 'Tipos de pago que no se ofrecerán. La lista autorizada para tu cuenta se obtiene con <code>GET /v1/payment_methods</code>. El dinero disponible en la cuenta de Mercado Pago no se puede excluir.';
$string['excludedpaymenttypes_help'] = 'Corresponde a <code>payment_methods.excluded_payment_types</code> en la preferencia.';
$string['excludedpaymentmethods'] = 'Medios de pago excluidos';
$string['excludedpaymentmethods_desc'] = 'Lista separada por comas de ids de medios de pago que no se ofrecerán, por ejemplo <code>master,amex</code>.';
$string['excludedpaymentmethods_help'] = 'Corresponde a <code>payment_methods.excluded_payment_methods</code> en la preferencia. Usá los ids devueltos por <code>GET /v1/payment_methods</code>.';
$string['defaultpaymentmethodid'] = 'Medio de pago preseleccionado';
$string['defaultpaymentmethodid_desc'] = 'Id del medio de pago preseleccionado en el checkout. Dejalo vacío para no preseleccionar ninguno.';
$string['defaultpaymentmethodid_help'] = 'Corresponde a <code>payment_methods.default_payment_method_id</code> en la preferencia.';
$string['custommetadata'] = 'Metadatos personalizados';
$string['custommetadata_desc'] = 'Un par <code>clave=valor</code> por línea. Se agregan al objeto <code>metadata</code> de la preferencia y vuelven en el pago, lo que los hace útiles para conciliar con tu sistema contable. Nunca pongas datos personales acá.';
$string['custommetadata_help'] = 'Un par <code>clave=valor</code> por línea. Las claves se pasan a minúsculas y los caracteres no alfanuméricos se reemplazan por guiones bajos. El plugin siempre envía los ids de sitio, curso, usuario y transacción de Moodle.';
$string['itemdescription'] = 'Descripción del ítem';
$string['itemdescription_help'] = 'Se muestra al comprador en el checkout de Mercado Pago como descripción de lo que está pagando. Dejalo vacío para usar el nombre del curso.';
$string['itemdescription_default'] = 'Inscripción en {$a}';
$string['categoryid'] = 'Categoría del ítem';
$string['categoryid_help'] = 'El <code>category_id</code> del ítem de Mercado Pago. <code>learnings</code> es la categoría de cursos y capacitación.';

// Split payments.
$string['splitpayments'] = 'Split payments (marketplace)';
$string['marketplaceenabled'] = 'Habilitar split payments';
$string['marketplaceenabled_desc'] = 'Permite que los cursos cobren en la cuenta de un vendedor conectado mientras tu marketplace se queda con una comisión.';
$string['marketplaceclientid'] = 'Client id de la aplicación';
$string['marketplaceclientid_desc'] = 'El client id (número de aplicación) de tu aplicación marketplace de Mercado Pago.';
$string['marketplaceclientsecret'] = 'Client secret de la aplicación';
$string['marketplaceclientsecret_desc'] = 'El client secret de tu aplicación marketplace de Mercado Pago. Se usa únicamente para canjear los códigos OAuth por tokens de vendedor.';
$string['marketplacename'] = 'Identificador del marketplace';
$string['marketplacename_desc'] = 'Valor opcional enviado en el campo <code>marketplace</code> de la preferencia.';
$string['splitenabled'] = 'Usar split payments en este curso';
$string['splitenabled_help'] = 'Cuando está activo, la preferencia de pago se crea con el access token del vendedor conectado y tu comisión viaja en <code>marketplace_fee</code>.';
$string['marketplacefee'] = 'Comisión del marketplace';
$string['marketplacefee_help'] = 'Importe, en la moneda del curso, que tu marketplace retiene de cada pago. Debe ser menor que el precio del curso. Mercado Pago descuenta primero su propia comisión y después esta.';
$string['sellerid'] = 'Id del vendedor';
$string['sellerid_help'] = 'El id de usuario de Mercado Pago del vendedor que cobra este curso. Se completa automáticamente al conectar un vendedor.';
$string['sellerconnection'] = 'Cuenta del vendedor';
$string['connectseller'] = 'Conectar un vendedor de Mercado Pago';
$string['reconnectseller'] = 'Reconectar el vendedor de Mercado Pago';
$string['sellerconnected'] = 'El vendedor de Mercado Pago {$a} quedó conectado a este método de inscripción.';

// Comportamiento.
$string['pendingholding'] = 'Reservar el lugar para pagos pendientes';
$string['pendingholding_desc'] = 'Crea una inscripción suspendida apenas se genera un pago offline (cupón de pago en efectivo, transferencia) y la activa cuando el dinero se acredita.';
$string['pendingholding_help'] = 'Una inscripción suspendida no da acceso al curso, pero aparece en la lista de participantes para que los docentes vean quién está en medio del pago.';
$string['reversalaction'] = 'Acción ante devolución o contracargo';
$string['reversalaction_desc'] = 'Qué hacer con la inscripción cuando un pago se devuelve, se cancela o recibe un contracargo.';
$string['reversalaction_help'] = 'La inscripción solo se toca cuando ningún otro pago aprobado del mismo usuario sigue cubriendo este curso.';
$string['reversalkeep'] = 'Mantener la inscripción';
$string['reversalsuspend'] = 'Suspender la inscripción y quitar el rol';
$string['reversalunenrol'] = 'Desinscribir al usuario';
$string['notifications'] = 'Enviar notificaciones';
$string['notifications_desc'] = 'Notifica a los compradores el resultado de su pago y avisa al equipo del curso sobre aprobaciones y reversiones.';
$string['notifications_help'] = 'Cada destinatario puede desactivar notificaciones puntuales en sus propias preferencias.';
$string['expiredaction'] = 'Acción al vencer la inscripción';
$string['expiredaction_desc'] = 'Qué hacer cuando termina el período de inscripción pagado.';
$string['cleanupafter'] = 'Período de retención';
$string['cleanupafter_desc'] = 'Se eliminan los checkouts abandonados y las filas del registro de webhooks ya procesadas más antiguas que esto. Las transacciones que generaron un pago se conservan siempre.';
$string['usesitedefault'] = 'Usar el valor del sitio';

// Diagnóstico.
$string['debuglogging'] = 'Registro detallado';
$string['debuglogging_desc'] = 'Escribe una línea en el registro de errores del servidor por cada llamada a la API de Mercado Pago. Las credenciales y los datos personales se ocultan, pero genera mucho ruido: dejalo desactivado en producción.';
$string['apitimeout'] = 'Tiempo de espera de la API';
$string['apitimeout_desc'] = 'Segundos de espera al conectar con api.mercadopago.com.';
$string['apimaxretries'] = 'Reintentos de la API';
$string['apimaxretries_desc'] = 'Cuántas veces el SDK reintenta una solicitud fallida antes de abandonar.';
$string['integratorid'] = 'Integrator id';
$string['integratorid_desc'] = 'Integrator id opcional provisto por Mercado Pago para el seguimiento de partners.';
$string['platformid'] = 'Platform id';
$string['platformid_desc'] = 'Platform id opcional provisto por Mercado Pago para el seguimiento de plataformas.';

// Formulario de instancia.
$string['instancedescription'] = 'Descripción breve';
$string['instancedescription_help'] = 'Se muestra debajo del nombre del método en la página de métodos de inscripción, para distinguir varias opciones de precio.';
$string['status'] = 'Permitir inscripciones por Mercado Pago';
$string['status_desc'] = 'Si las instancias nuevas se crean habilitadas. Una instancia solo puede habilitarse si existen credenciales válidas y el sitio se sirve por HTTPS.';
$string['status_help'] = 'Al deshabilitarlo el método deja de aceptar pagos nuevos; las inscripciones existentes no se ven afectadas.';
$string['cost'] = 'Costo de inscripción';
$string['cost_help'] = 'El precio del curso en la moneda seleccionada. Debe ser mayor que cero para poder habilitar el método.';
$string['currency'] = 'Moneda';
$string['assignrole'] = 'Asignar rol';
$string['assigngroup'] = 'Agregar al grupo';
$string['assigngroup_help'] = 'El comprador se agrega a este grupo cuando se aprueba el pago. La pertenencia al grupo no se quita si luego se revierte el pago.';
$string['enrolperiod'] = 'Duración de la inscripción';
$string['enrolperiod_desc'] = 'Duración por defecto de la inscripción comprada. 0 significa ilimitada.';
$string['enrolperiod_help'] = 'Cuánto dura el acceso comprado, contado desde el momento en que se aprueba el pago. Dejalo vacío para acceso ilimitado.';
$string['enrolstartdate'] = 'Fecha de inicio';
$string['enrolenddate'] = 'Fecha de finalización';
$string['maxenrolled'] = 'Máximo de usuarios inscriptos';
$string['maxenrolled_help'] = 'Cuando esta cantidad de usuarios tenga una inscripción activa por este método, no se podrán iniciar pagos nuevos. 0 significa sin límite.';
$string['defaultrole'] = 'Rol por defecto';
$string['defaultrole_desc'] = 'Rol asignado a quienes pagan por este método.';
$string['paymentbehaviour'] = 'Comportamiento del pago';
$string['advancedpreference'] = 'Opciones avanzadas de Checkout Pro';

// Página de inscripción.
$string['paybutton'] = 'Pagar con Mercado Pago';
$string['redirectnotice'] = 'Te vamos a llevar a Mercado Pago para completar el pago y después volvés acá.';
$string['installmentsavailable'] = 'Hasta {$a} cuotas disponibles.';
$string['installmentsx'] = '({$a} cuotas)';
$string['testmodenotice'] = 'Este sitio está usando Mercado Pago en modo de prueba. No se cobrará dinero real.';
$string['pendingpaymentnotice'] = 'Ya tenés un pago en proceso ({$a}). Vas a quedar inscripto automáticamente apenas se acredite.';

// Página de resultado.
$string['paymentresult'] = 'Resultado del pago';
$string['result_approved_title'] = 'Pago aprobado';
$string['result_approved_body'] = 'Tu pago se acreditó y ya tenés acceso al curso.';
$string['result_pending_title'] = 'Pago pendiente';
$string['result_pending_body'] = 'Mercado Pago todavía no acreditó este pago. Vas a quedar inscripto automáticamente apenas lo haga; no hace falta que hagas nada más.';
$string['result_rejected_title'] = 'Pago no completado';
$string['result_rejected_body'] = 'El pago no se completó, así que no se creó ninguna inscripción. Podés volver a intentarlo con otro medio de pago.';
$string['result_unknown_title'] = 'Pago no iniciado';
$string['result_unknown_body'] = 'Mercado Pago no informó ningún pago para este checkout. Si ya pagaste, esperá unos minutos y recargá esta página.';
$string['result_review_title'] = 'Pago en revisión';
$string['result_review_body'] = 'Mercado Pago está revisando este pago. La inscripción se va a actualizar automáticamente cuando termine la revisión.';
$string['keepthisreference'] = 'Guardá la referencia de arriba por si necesitás contactar al soporte por este pago.';

// Reporte.
$string['transactions'] = 'Transacciones de Mercado Pago';
$string['paymentstatus'] = 'Estado del pago';
$string['paymentmethod'] = 'Medio de pago';
$string['paymentid'] = 'Id de pago';
$string['externalreference'] = 'Referencia';
$string['enrolmentstate'] = 'Inscripción';
$string['lastupdate'] = 'Última actualización';
$string['allstatuses'] = 'Todos los estados';
$string['deleteduser'] = 'Usuario eliminado';
$string['testmode'] = 'Prueba';
$string['reconcilenow'] = 'Reconsultar en Mercado Pago';
$string['reconcileresult'] = 'Reconsulta finalizada: {$a}';

// Estados de pago.
$string['status_created'] = 'Checkout iniciado';
$string['status_approved'] = 'Aprobado';
$string['status_authorized'] = 'Autorizado';
$string['status_in_process'] = 'En proceso';
$string['status_pending'] = 'Pendiente';
$string['status_cancelled'] = 'Cancelado';
$string['status_charged_back'] = 'Contracargo';
$string['status_in_mediation'] = 'En mediación';
$string['status_refunded'] = 'Devuelto';
$string['status_rejected'] = 'Rechazado';
$string['status_unknown'] = 'Desconocido ({$a})';

$string['enrolmentstate_none'] = 'Sin inscripción';
$string['enrolmentstate_pending'] = 'Lugar reservado';
$string['enrolmentstate_active'] = 'Activa';
$string['enrolmentstate_suspended'] = 'Suspendida';
$string['enrolmentstate_unenrolled'] = 'Desinscripto';

// Tipos de pago.
$string['paymenttype_credit_card'] = 'Tarjeta de crédito';
$string['paymenttype_debit_card'] = 'Tarjeta de débito';
$string['paymenttype_prepaid_card'] = 'Tarjeta prepaga';
$string['paymenttype_ticket'] = 'Cupón de pago en efectivo';
$string['paymenttype_bank_transfer'] = 'Transferencia bancaria';
$string['paymenttype_atm'] = 'Cajero automático';

// Errores.
$string['error:apicall'] = 'Falló la llamada a Mercado Pago ({$a->operation}, HTTP {$a->status}).';
$string['error:sdkmissing'] = 'El SDK PHP de Mercado Pago no está disponible. Instalalo en enrol/mpcheckoutpro/vendor antes de usar este método de inscripción.';
$string['error:nocredentials'] = 'No hay credenciales de Mercado Pago configuradas para este método de inscripción.';
$string['error:httpsrequired'] = 'Mercado Pago requiere HTTPS para las URLs de notificación y de retorno. Este sitio no se sirve por HTTPS.';
$string['error:nocost'] = 'No se definió un costo de inscripción para este método.';
$string['error:costnotnumeric'] = 'El costo de inscripción debe ser un número.';
$string['error:costpositive'] = 'El costo de inscripción debe ser mayor que cero para poder habilitar este método.';
$string['error:enrolenddate'] = 'La fecha de finalización no puede ser anterior a la de inicio.';
$string['error:unsupportedcurrency'] = 'Mercado Pago no admite la moneda {$a} en los sitios que cubre este plugin.';
$string['error:instancedisabled'] = 'Este método de inscripción está deshabilitado.';
$string['error:mustbeloggedin'] = 'Tenés que iniciar sesión con una cuenta real para pagar un curso.';
$string['error:enrolmentnotopen'] = 'La inscripción a este curso todavía no abrió.';
$string['error:enrolmentclosed'] = 'La inscripción a este curso está cerrada.';
$string['error:alreadyenrolled'] = 'Ya estás inscripto en este curso por este método.';
$string['error:coursefull'] = 'Este método de inscripción alcanzó su máximo de usuarios inscriptos.';
$string['error:ratelimited'] = 'Demasiados intentos de pago en poco tiempo. Esperá un minuto y volvé a intentarlo.';
$string['error:preferencefailed'] = 'No se pudo crear el pago en Mercado Pago. Volvé a intentarlo en unos minutos.';
$string['error:noinitpoint'] = 'Mercado Pago no devolvió una URL de checkout para este pago.';
$string['error:unknowntransaction'] = 'Esta transacción de pago no existe.';
$string['error:referencemismatch'] = 'La referencia del pago no coincide con esta transacción.';
$string['error:notavailable'] = 'Este método de inscripción no está disponible en este momento. Contactá al administrador del curso.';
$string['error:installmentsrange'] = 'La cantidad máxima de cuotas debe estar entre 0 y 36.';
$string['error:definstallmentsrange'] = 'Las cuotas preseleccionadas deben estar entre 0 y la cantidad máxima de cuotas.';
$string['error:feenotnumeric'] = 'La comisión del marketplace debe ser un número.';
$string['error:feetoolarge'] = 'La comisión del marketplace debe ser menor que el costo de inscripción.';
$string['error:invalidmethodid'] = 'Los ids de medios de pago solo pueden contener minúsculas, números y guiones bajos.';
$string['error:marketplacedisabled'] = 'Split payments no está habilitado en este sitio.';
$string['error:oauthdenied'] = 'El vendedor de Mercado Pago no autorizó la conexión ({$a}).';
$string['error:oauthincomplete'] = 'Mercado Pago no devolvió un código de autorización.';
$string['error:oauthstate'] = 'No se pudo asociar la respuesta de autorización a una solicitud. Volvé a iniciar la conexión.';
$string['error:oauthexchange'] = 'No se pudo canjear el código de autorización por un token de vendedor.';

// Eventos.
$string['event:preference_created'] = 'Preferencia de checkout creada';
$string['event:payment_approved'] = 'Pago aprobado';
$string['event:payment_reversed'] = 'Pago revertido';
$string['event:payment_updated'] = 'Estado de pago actualizado';
$string['event:webhook_received'] = 'Notificación de Mercado Pago recibida';
$string['event:webhook_rejected'] = 'Notificación de Mercado Pago rechazada';

// Tareas.
$string['task:reconcile_payments'] = 'Conciliar pagos de Mercado Pago';
$string['task:retry_webhooks'] = 'Reintentar notificaciones de Mercado Pago';
$string['task:process_expirations'] = 'Procesar vencimientos de inscripciones de Mercado Pago';
$string['task:cleanup_records'] = 'Limpiar registros de Mercado Pago';

// Mensajes.
$string['messageprovider:payment_approved'] = 'Tu pago de Mercado Pago fue aprobado';
$string['messageprovider:payment_pending'] = 'Tu pago de Mercado Pago está pendiente';
$string['messageprovider:payment_failed'] = 'Tu pago de Mercado Pago no se completó';
$string['messageprovider:payment_reversed'] = 'Tu pago de Mercado Pago fue revertido';
$string['messageprovider:teacher_notification'] = 'Actividad de pagos de Mercado Pago en tus cursos';
$string['messageprovider:expiry_notification'] = 'Avisos de vencimiento de inscripciones de Mercado Pago';

$string['message_payment_approved_subject'] = 'Pago aprobado: {$a->coursename}';
$string['message_payment_approved_body'] = 'Hola {$a->fullname}:

Tu pago de {$a->amount} por "{$a->coursename}" fue aprobado y ya estás inscripto.

Ir al curso: {$a->courseurl}

Id de pago de Mercado Pago: {$a->paymentid}';

$string['message_payment_pending_subject'] = 'Pago pendiente: {$a->coursename}';
$string['message_payment_pending_body'] = 'Hola {$a->fullname}:

Tu pago de {$a->amount} por "{$a->coursename}" todavía no se acreditó ({$a->status}).

No hace falta que hagas nada más: vas a quedar inscripto automáticamente apenas Mercado Pago acredite el pago.

Id de pago de Mercado Pago: {$a->paymentid}';

$string['message_payment_failed_subject'] = 'Pago no completado: {$a->coursename}';
$string['message_payment_failed_body'] = 'Hola {$a->fullname}:

Tu pago de {$a->amount} por "{$a->coursename}" no se completó ({$a->status}).

No se realizó ningún cobro y no quedaste inscripto. Podés volver a intentarlo desde la página de inscripción del curso:
{$a->courseurl}';

$string['message_payment_reversed_subject'] = 'Pago revertido: {$a->coursename}';
$string['message_payment_reversed_body'] = 'Hola {$a->fullname}:

El pago de {$a->amount} por "{$a->coursename}" fue revertido ({$a->status}) el {$a->date}, así que se retiró tu acceso al curso.

Si creés que se trata de un error, contactá al administrador del curso citando el id de pago {$a->paymentid}.';

$string['message_staffapproved_subject'] = 'Nueva inscripción paga: {$a->coursename}';
$string['message_staffapproved_body'] = '{$a->fullname} pagó {$a->amount} por "{$a->coursename}" y quedó inscripto.

Id de pago de Mercado Pago: {$a->paymentid}
Fecha: {$a->date}';

$string['message_staffreversed_subject'] = 'Pago revertido: {$a->coursename}';
$string['message_staffreversed_body'] = 'El pago de {$a->amount} realizado por {$a->fullname} por "{$a->coursename}" fue revertido ({$a->status}).

Id de pago de Mercado Pago: {$a->paymentid}
Fecha: {$a->date}';

$string['expirymessageenrolledsubject'] = 'Aviso de vencimiento de inscripción de Mercado Pago';
$string['expirymessageenrolledbody'] = 'Estimado/a {$a->user}:

Le informamos que su inscripción en el curso \'{$a->course}\', pagada a través de Mercado Pago, vence el {$a->timeend}.

Si necesita ayuda, contacte a {$a->enroller}.';
$string['expirymessageenrollersubject'] = 'Aviso de vencimiento de inscripciones de Mercado Pago';
$string['expirymessageenrollerbody'] = 'Las inscripciones por Mercado Pago en el curso \'{$a->course}\' vencerán dentro de los próximos {$a->threshold} para los siguientes usuarios:

{$a->users}

Para cambiarlo, ingresá a {$a->extendurl}';

// Privacidad.
$string['privacy:metadata:txn'] = 'Información sobre los pagos hechos con Mercado Pago Checkout Pro para inscribirse en un curso.';
$string['privacy:metadata:txn:userid'] = 'El id del usuario que hizo el pago.';
$string['privacy:metadata:txn:courseid'] = 'El id del curso por el que se pagó.';
$string['privacy:metadata:txn:externalreference'] = 'La referencia que este sitio envió a Mercado Pago para identificar el pago.';
$string['privacy:metadata:txn:preferenceid'] = 'El id de la preferencia de pago de Mercado Pago.';
$string['privacy:metadata:txn:paymentid'] = 'El id del pago de Mercado Pago.';
$string['privacy:metadata:txn:status'] = 'El estado del pago.';
$string['privacy:metadata:txn:amount'] = 'El importe pagado.';
$string['privacy:metadata:txn:currency'] = 'La moneda del pago.';
$string['privacy:metadata:txn:paymentmethodid'] = 'El medio de pago utilizado.';
$string['privacy:metadata:txn:installments'] = 'La cantidad de cuotas elegida.';
$string['privacy:metadata:txn:timecreated'] = 'Cuándo se inició el checkout.';
$string['privacy:metadata:txn:timeapproved'] = 'Cuándo se aprobó el pago.';

$string['privacy:metadata:mercadopago'] = 'Para cobrar un pago hay que enviar algunos datos a Mercado Pago.';
$string['privacy:metadata:mercadopago:email'] = 'La dirección de correo del comprador, para que Mercado Pago pueda identificarlo en el checkout.';
$string['privacy:metadata:mercadopago:firstname'] = 'El nombre del comprador.';
$string['privacy:metadata:mercadopago:lastname'] = 'El apellido del comprador.';
$string['privacy:metadata:mercadopago:external_reference'] = 'Una referencia interna que identifica la compra en este sitio.';
$string['privacy:metadata:mercadopago:metadata'] = 'Los ids internos de sitio, curso, usuario y transacción de Moodle de la compra.';
$string['privacy:metadata:mercadopago:item'] = 'El nombre y el precio del curso que se está comprando.';

// ------------------------------------------------- Mensaje de bienvenida al curso.
$string['sendcoursewelcomemessage'] = 'Enviar mensaje de bienvenida al curso';
$string['sendcoursewelcomemessage_desc'] = 'Valor por defecto para las instancias nuevas. Si se envía un mensaje de bienvenida cuando se aprueba el pago, y de parte de quién aparece enviado.';
$string['sendcoursewelcomemessage_help'] = 'El mensaje de bienvenida se envía una sola vez, cuando el pago se aprueba y la matriculación queda activa. No se envía con un pago pendiente, ni se vuelve a enviar si un pago revertido se reactiva más adelante.

La opción "De parte del poseedor de la clave" que ofrece el núcleo no está disponible acá: resuelve el remitente a través de la capacidad del poseedor de la clave de auto-matriculación, y una matriculación paga no tiene clave.';
