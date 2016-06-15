import co2mpas.dispatcher.utils as dsp_utl
import numpy as np
import pandas as pd
import os.path as osp
from datetime import datetime
from math import pi
from scipy.interpolate import InterpolatedUnivariateSpline as Spline
from collections import OrderedDict

mydir = osp.dirname(__file__)

### Do the runs of the measured cars / Correct the inertia first ???

_database_f = osp.join(mydir, 'data', 'testing.csv')
_parameters_f =  osp.join(mydir, 'data', 'params.txt')
_measured_cars_f =  osp.join(mydir, 'data', 'parameters.xlsx')

out_f = osp.join(mydir, 'data', '_run_testing.csv')
outputs_f = osp.join(mydir, 'outputs')



def main():
    run(_database_f, write_results=False, measured_cars=True)
    return



def run(_database_f, write_results=False, measured_cars = False):
    
    df = prepare_measured_cars_run_inputs(_database_f, measured_cars)
    
    from co2mpas.models import vehicle_processing_model
    input = ['input_data', 'prediction_wltp']
    output = ['output_data']
    run = dsp_utl.SubDispatchFunction(vehicle_processing_model(), \
                                      'vpm', input, output)

    cases = df.T.to_dict().values()
    res = []
    for case in cases:
        try:
            if case['gear_box_type'] == 'automatic':
                ks = list(case.keys())
                for k in ks:
                    if k.split('-')[-1] == 'AT':
                        case[k.split('-')[0]] = case[k]
            
            nedc, wltp = case.copy(), case.copy()
    
            to_del_step1 = ['f0', 'f1', 'f2', 'vehicle_mass', 'initial_state_of_charge', 
                            'initial_temperature', 'cycle_type']
            for i in to_del_step1:
                del(nedc[i])
            ks = list(nedc.keys())
            for k in ks:
                if k.split('-')[-1] == 'N':
                    nedc[k.split('-')[0]] = nedc[k]
                    
            ################# WLTP #######################
            ###
            from co2mpas._parametric._get_wltp_profile import wltc_func 
            rd, fd, nr, gbr = wltp['r_dynamic'], wltp['final_drive_ratio'], \
                wltp['engine_max_speed_at_max_power'], wltp['gear_box_ratios']
            vmax = 3.6*rd*(nr/60/(fd*gbr[max(gbr.keys())]))*2*pi
            stv = [v for k, v in _calculate_stv(fd,rd,gbr).items()]
            rls = [wltp['f0'], wltp['f1'], wltp['f2']]
            
            mass_value = wltp['vehicle_mass']
            pr = wltp['engine_max_power']
            ni = wltp['idle_engine_speed_median']
            
            wltc = wltc_func(stv, mass_value, vmax, pr, nr, ni, rls, is_nedc=False)
            wltp['times'], wltp['velocities'] = wltc.time.values, wltc.velocity.values
            if wltp['gear_box_type'] == 'manual':
                wltp['gears'] = wltc.gear.values
    
            ins = {'nedc_inputs': nedc, 'wltp_h_inputs': wltp, 
                   'wltp_l_inputs': wltp, 'wltp_p_inputs': wltp}
            
            out = run(ins, 'True')
            
            wltp_co2_out = out['wltp_h']['predictions']['parameters']['co2_emission_value']
            nedc_co2_out = out['nedc']['predictions']['parameters']['co2_emission_value']
            print(wltp_co2_out, nedc_co2_out)
            wltp['wltp_parametric_co2'] = wltp_co2_out
            wltp['nedc_parametric_co2'] = nedc_co2_out
            res.append(wltp)
            
            if write_results == True:
                input = ['vehicle_name', 'with_charts', 'output_data',
                         'template_file_name', 'start_time', 'output_file_name']
                write_output = dsp_utl.SubDispatchFunction(vehicle_processing_model(), \
                                                  'vpm', input)
                
                name = wltp['model']
                write_output(name, 'True', out, None, datetime.today(), \
                             osp.join(outputs_f, "%s"%name))
            
        except:
            pass
   
    pd.DataFrame.from_records(res).to_csv(out_f)
    
    return

    
def prepare_measured_cars_run_inputs(_database_f, measured_cars = False):
    
    _dtypes = _read_dtypes()
    _database = _read_db(_database_f, _dtypes)
    _measured_cars_db = get_measured_cars_db(_measured_cars_f)
    
    _params = _read_params_txt(_parameters_f)
    
    ### PART OF THE DATABASE
    #
    # ----------------------------------------------------------
    if not measured_cars:
        rel_date = ('General Specifications', 'Release date')
        _database = _database[_database[rel_date]==2014]. \
                                            reset_index(drop=True)
        drive_system = ('Drive', 'Drive system')
        _database = _database[_database[drive_system]=='fuel engine']. \
                                            reset_index(drop=True)
    # ----------------------------------------------------------
    _database[('Drive', 'Fuel')] = _database[('Drive', 'Fuel')].replace('petrol', 'gasoline')
    _database[('Fuel Engine', 'Turbo')] = _database[('Fuel Engine', 'Turbo')].replace(['no', 'yes'], [0, 1])
    _database[('Comfort', 'Start / stop system')] = _database[('Comfort', 'Start / stop system')].replace(['no', 'yes'], [0, 1])
    _database[('Chassis', 'Rolling Radius Dynamic')] = _database[('Chassis', 'Rolling Radius Dynamic')]/1000 
    _database[('Drive', 'Wheel drive')] = _database[('Drive', 'Wheel drive')]. \
            replace(['front', 'rear', 'onbekend', '', 'front+rear'], [2,2,2,2,4])
    
    from _road_loads import calc_rls
    _database = calc_rls(_database)
    
    
    _avm = _assign_values(_database, _measured_cars_db)
    _add = ['f0-N', 'f1-N', 'f2-N', 'vehicle_mass-N', 'initial_state_of_charge-N', 
            'initial_temperature-N', 'cycle_type-N',
            'CMV-AT', 'MVL-AT', 'eco_mode-AT']
    for p in _params+_add:
        if p in _avm.keys():
            _database[('CO2MPAS Inputs', p)] = _avm[p]
            
    _db_co2mpas_in = _database['CO2MPAS Inputs']
    _db_co2mpas_in['model'] = _database[('Vehicle', 'Model')]
    _db_co2mpas_in['_target_co2'] = _database[('Performance', 'CO2 Emission')]
#     _db_co2mpas_in['wltp_target_pred_co2'] = _database[('WLTP', 'Prediction CO2')]
#     _db_co2mpas_in['wltp_target_calib_co2'] = _database[('WLTP', 'Calibration CO2')]
#     _db_co2mpas_in['wltp_target_co2'] = _database[('WLTP', 'Target CO2')]
#     _db_co2mpas_in['nedc_target_pred_co2'] = _database[('NEDC', 'Prediction CO2')]
#     _db_co2mpas_in['nedc_target_co2'] = _database[('NEDC', 'Target CO2')]
#     _db_co2mpas_in.to_csv(_co2mpas_in_f)
    _db_co2mpas_in['unladen_mass'] = _database[('Weights', 'Unladen mass')]
    _db_co2mpas_in['running_order_mass'] = _database[('Weights', 'Running order mass')]


    return _db_co2mpas_in


def _assign_values(df, dr):
    
    _bat_cap, _idle_fc, _max_rpm, _idle_speed, _ss_activ_time, _max_charg_cur, \
        _start_dem, _el_load_off, _el_load_on, _co2_params, _cmv, _mvl = extract_functions(dr)
    
    capacity = df[('Fuel Engine', 'Capacity')]
    fuel = df[('Drive', 'Fuel')]
    fuel = fuel.replace(['LPG', 'gas', 'bioethanol'], ['gasoline', 'gasoline', 'diesel'])
    turbo = df[('Fuel Engine', 'Turbo')]
    
    df[('Drive', 'Engine Type')] = fuel
    gt = (fuel=='gasoline') & (turbo==1)
    na = (fuel=='gasoline') & (turbo==0)
    df.ix[gt, ('Drive', 'Engine Type')] = 'gasoline turbo'
    df.ix[na, ('Drive', 'Engine Type')] = 'gasoline natural aspiration'
    
    
    
#     df[('Fuel Engine', 'co2_params')] = df.apply(lambda x: _co2_params[x[('Drive', 'Engine Type')]], axis=1)
    
    df[('Fuel Engine', 'co2_params')] = df.apply(lambda x: get_co2_params_new(
                                        x[('Fuel Engine', 'Max power')], 
                                        x[('Fuel Engine', 'Capacity')], 
                                        x[('Drive', 'Engine Type')]), axis=1)
    
    
    df['heating_value'] = df.apply(lambda x: heating_value(x[('Drive', 'Fuel')]), axis=1)
    df['carbon_content'] = df.apply(lambda x: carbon_content(x[('Drive', 'Fuel')]), axis=1)
    df['trg'] = df.apply(lambda x: x[('Fuel Engine', 'co2_params')]['trg'], axis=1)
    df['gbrs'] = df[('Transmission  / Gear ratio', 'Gear Box Ratios')]
    df['gear_box_ratios'] = df['gbrs'].apply(lambda x: {eval(x).index(i)+1:i for i in eval(x)})
    
    df['el_load_off'] = _el_load_off(capacity)
    df['el_load_on'] = _el_load_on(capacity)
    df['el_load'] = df[['el_load_off', 'el_load_on']].apply(tuple, axis=1)
    
    anp, ae, anv = dr['alternator_nominal_power'].mean(), \
        dr['alternator_efficiency'].mean(), dr['alternator_nominal_voltage'].mean()
    df['acc1'] = -anp*1000*ae/anv
    df['acc2'] = df['acc1']*0.2
    df['acc3'] = 0.0
    df['alternator_charging_currents'] = df[['acc1', 'acc2', 'acc3']].apply(tuple, axis=1)
    
    df['r_dynamic'] = df[('Chassis', 'Rolling Radius Dynamic')]
    df['final_drive_ratio'] = df[('Transmission  / Gear ratio', 'Final drive')]
    
    l = len(df)
    rd = df['r_dynamic'].values
    fd = df['final_drive_ratio'].values
    gb = df['gear_box_ratios'].values
    stvs = []
    cmvs = []
    mvls = []
    for i in np.arange(l):
        try: # Some (e.g. 939 have wrong gb ratios - same ratio - such that stv construction above miss a key/index
            stv = _calculate_stv(fd[i], rd[i], gb[i])
            cmv_at = _cmv(stv)
            mvl_at = _mvl(stv)
            stvs.append(stv)
            cmvs.append(cmv_at)
            mvls.append(mvl_at)
        except:
            stvs.append(stv)
            cmvs.append(0)
            mvls.append(0)
            
    df['stv'] = np.array(stvs)
    df['CMV_AT'] = np.array(cmvs)
    df['MVL_AT'] = np.array(mvls)
#     df['stv'] = df.apply(lambda x: _calculate_stv(x['final_drive_ratio'], \
#                                     x['r_dynamic'], x['gear_box_ratios']), axis=1)
    
#     df['CMV_AT'] = df.apply(lambda x: _cmv(x['stv']), axis=1)
#     df['MVL_AT'] = df.apply(lambda x: _mvl(x['stv']), axis=1)
    
    assign_values_to_params = {
        'alternator_efficiency': ae, 
        'alternator_nominal_voltage': anv,
        'battery_capacity': _bat_cap(capacity),
        'engine_capacity': capacity,
        'engine_fuel_lower_heating_value': df['heating_value'],
        'engine_idle_fuel_consumption': _idle_fc(capacity),
        'engine_is_turbo': turbo,
        'engine_max_power': df[('Fuel Engine', 'Max power')],
        'engine_max_speed': _max_rpm(df[('Fuel Engine', 'Max power RPM')]),
        'engine_max_speed_at_max_power': df[('Fuel Engine', 'Max power RPM')],
        'engine_max_torque': df[('Fuel Engine', 'Max torque')],
        'engine_stroke': df[('Fuel Engine', 'Stroke')],
        'final_drive_ratio': df['final_drive_ratio'],
        'fuel_carbon_content': df['carbon_content'],
        'fuel_type': fuel,
        'gear_box_ratios': df['gear_box_ratios'],
        'gear_box_type': df[('General Specifications', 'Transmission')],
        'has_energy_recuperation': 1,
        'has_start_stop': df[('Comfort', 'Start / stop system')],
        'idle_engine_speed_median': _idle_speed(capacity),

        'f0': df[('WLTP', 'f0')],
        'f1': df[('WLTP', 'f1')],
        'f2': df[('WLTP', 'f2')],
        'vehicle_mass': df[('Weights', 'Reference mass')],
        'initial_state_of_charge': 80,
        'initial_temperature': 23,
        'cycle_type': 'WLTP',

        'f0-N': df[('NEDC', 'f0')],
        'f1-N': df[('NEDC', 'f1')],
        'f2-N': df[('NEDC', 'f2')],
        'vehicle_mass-N': df[('Weights', 'Inertia mass')],
        'initial_state_of_charge-N': 99,
        'initial_temperature-N': 25,
        'cycle_type-N': 'NEDC',
        
        'r_dynamic': df['r_dynamic'],
        'start_stop_activation_time': _ss_activ_time(capacity),
        'alternator_nominal_power': anp,
        'n_wheel_drive': df[('Drive', 'Wheel drive')],
        'delta_speed_cold': 50,
        'state_of_charge_balance': 80,
        'state_of_charge_balance_window': 1,
        'max_battery_charging_current': _max_charg_cur(capacity),
        'start_demand': _start_dem(capacity),
        'electric_load': df['el_load'],
        'alternator_charging_currents': df['alternator_charging_currents'],
        'engine_thermostat_temperature': df['trg'],
        'temperature_max': df['trg']+10,
        'temperature_threshold': df['trg']-5,
        'temperature_increase_when_engine_off': 0,
        'engine_coolant_flow': 0.008,
        'engine_coolant_constant': 0.4,
        'heat_to_engine': 0.35,
        'CMV-AT': df['CMV_AT'],
        'MVL-AT': df['MVL_AT'],
        'eco_mode-AT': 'True',
        'co2_params': df[('Fuel Engine', 'co2_params')],
#         'times'
#         'velocities'
#         'gears'
        'air_density': 1.2,
        'angle_slope': 0,
        'auxiliaries_torque_loss': 0.5,
        }
    
    return assign_values_to_params

def extract_functions(db):
    
    _d = db[['engine_capacity', 'battery_capacity']].dropna()
    eng_cap, bat_cap = _d['engine_capacity'], _d['battery_capacity']
    _bat_cap = np.poly1d(np.polyfit(eng_cap, bat_cap, deg=1))
    
    _d = db[['engine_capacity', 'engine_idle_fuel_consumption']].dropna()
    eng_cap, idle_fc = _d['engine_capacity'], _d['engine_idle_fuel_consumption']
    _idle_fc = np.poly1d(np.polyfit(eng_cap, idle_fc, deg=1))
    
    _d = db[['engine_max_speed_at_max_power', 'engine_max_speed']].dropna()
    max_power_rpm, max_rpm = _d['engine_max_speed_at_max_power'], _d['engine_max_speed']
    _max_rpm = np.poly1d(np.polyfit(max_power_rpm, max_rpm, deg=1))
    
    _d = db[['engine_capacity', 'idle_engine_speed_median']].dropna()
    eng_cap, idle_speed = _d['engine_capacity'], _d['idle_engine_speed_median']
    _idle_speed = np.poly1d(np.polyfit(eng_cap, idle_speed, deg=1))

    _d = db[['engine_capacity', 'start_stop_activation_time']].dropna()
    eng_cap, ss_act_time = _d['engine_capacity'], _d['start_stop_activation_time']
    _ss_activ_time = np.poly1d(np.polyfit(eng_cap[ss_act_time<1800], ss_act_time[ss_act_time<1800], deg=1))
    
    _d = db[['engine_capacity', 'max_battery_charging_current']].dropna()
    eng_cap, max_charg_cur = _d['engine_capacity'], _d['max_battery_charging_current']
    _max_charg_cur = np.poly1d(np.polyfit(eng_cap, max_charg_cur, deg=1))
    
    _d = db[['engine_capacity', 'start_demand']].dropna()
    eng_cap, start_dem = _d['engine_capacity'], _d['start_demand']
    _start_dem = np.poly1d(np.polyfit(eng_cap, start_dem, deg=1))
    
    
    _d = db[['engine_capacity', 'electric_load']].dropna()
    eng_cap = _d['engine_capacity']
    el_load_off = _d.apply(lambda x: eval(x['electric_load'])[0], axis=1)
    el_load_on = _d.apply(lambda x: eval(x['electric_load'])[1], axis=1)
    _el_load_off = np.poly1d(np.polyfit(eng_cap, el_load_off, deg=1))
    _el_load_on = np.poly1d(np.polyfit(eng_cap, el_load_on, deg=1))
    
    _d = db[['co2_params_calibrated', 'engine_type']].dropna()
    _co2_params = get_co2_params(_d)
    
    _d = db[['CMV', 'MVL', 'velocity_speed_ratios']].dropna()
    _cmv, _mvl = get_at_models_funcs(_d)
    
    return _bat_cap, _idle_fc, _max_rpm, _idle_speed, _ss_activ_time, _max_charg_cur, \
            _start_dem, _el_load_off, _el_load_on, _co2_params, _cmv, _mvl
            

def get_co2_params_new(max_power, capacity, eng_type):
    
    params = {
        'gasoline turbo': {
                'a': 0.8882*max_power/capacity+0.377,
                'b': -0.17988*(0.882*max_power/capacity+0.377)+0.0899,
                'c': -0.06492*(-0.17988*(0.882*max_power/capacity+0.377)+0.0899)+0.000117,
                'a2': -0.00266,
                'b2': 0,
                'l': -2.49882,
                'l2': -0.0025,
                't':2.7,
                'trg': 85.,
                },
        'gasoline natural aspiration': {
                'a': 0.8882*max_power/capacity+0.377,
                'b': -0.17988*(0.882*max_power/capacity+0.377)+0.0899,
                'c': -0.06492*(-0.17988*(0.882*max_power/capacity+0.377)+0.0899)+0.000117,
                'a2': -0.00385,
                'b2': 0,
                'l': -2.14063,
                'l2': -0.00286,
                't': 2.7,
                'trg': 85.,
                },
        'diesel': {
                'a': -0.0005*max_power+0.438451,
                'b': -0.26503*(-0.0005*max_power+0.43845)+0.12675,
                'c': -0.08528*(-0.26503*(-0.0005*max_power+0.43845)+0.12675)+0.0003,
                'a2': -0.0012,
                'b2': 0,
                'l': -1.55291,
                'l2': -0.0076,
                't': 2.7,
                'trg': 85.,
                }
        }
    
    return params[eng_type]     



def get_cmv_values(x):
    import re
    m = re.findall('\([0-9],\s\((.+?)\)', x)
    mm = [i.split(',') for i in m]
    d = {}
    for i in np.arange(len(mm)):
        if i < len(mm)-1:
            d[i]=(eval(mm[i][0]), eval(mm[i][1]))
        else:
            d[i]=(eval(mm[i][0]), 10000)
    return d

def get_mvl_values(x):
    import re
    m = re.findall('\([0-9],\s\((.+?)\)', x)
    mm = [i.split(',') for i in m]
    d = {}
    for i in np.arange(len(mm)):
        d[(len(mm)-1-i)]=(eval(mm[i][0]), eval(mm[i][1]))
    return d

def calc_cms(x, y):
    y[0] = 0
    d = {}
    for k, v in x.items():
        if y[k] == 0:
            d[k] = (0, 10000)
        else:
            d[k] = (v[0]/y[k], v[1]/y[k])
    return d

def _avg_limits(cc):
    dl, du = {}, {}
    for i in np.arange(10+1):
        ll, lu = [], []
        for c in cc:
            if i in c.keys():
                if c[i][0] < 3500:
                    ll.append(c[i][0])
                if c[i][1] < 3500:
                    lu.append(c[i][1])
        dl[i] = ll
        du[i] = lu
    for d in [dl, du]:
        for k, v in d.items():
            d[k] = np.mean(v)
    dl = {k:v for k,v in dl.items() if not np.isnan(v)}
    du = {k:v for k,v in du.items() if not np.isnan(v)}  
    return dl, du


def get_at_models_funcs(db):
    from co2mpas.functions.co2mpas_model.physical.gear_box.AT_gear import CMV
    from co2mpas.functions.co2mpas_model.physical.gear_box.AT_gear import MVL
    
    db['cmv_'] = db.apply(lambda x: get_cmv_values(x['CMV']), axis=1)
    db['mvl_'] = db.apply(lambda x: get_mvl_values(x['MVL']), axis=1)
    db['vsr_'] = db['velocity_speed_ratios'].apply(lambda x: eval(x))
    
    db['cms_'] = db.apply(lambda x: calc_cms(x['cmv_'], x['vsr_']), axis=1)
    db['msl_'] = db.apply(lambda x: calc_cms(x['mvl_'], x['vsr_']), axis=1)    
    
    cms = db['cms_'].values
    msl = db['msl_'].values
    
    dl_cms, du_cms = _avg_limits(cms)
    dl_msl, du_msl = _avg_limits(msl)
    
    _dls_cms = Spline(list(dl_cms.keys()), list(dl_cms.values()), k=1) 
    _dus_cms = Spline(list(du_cms.keys())[1:], list(du_cms.values())[1:], k=1) 
    _dls_msl = Spline(list(dl_msl.keys()), list(dl_msl.values()), k=1) 
    _dus_msl = Spline(list(du_msl.keys())[1:], list(du_msl.values())[1:], k=1) 
    
    def _cms_mean(x):
        return _dls_cms(x), _dus_cms(x)

    def _msl_mean(x):
        return _dls_msl(x), _dus_msl(x)
    
    def cms_to_cmv(stv):
        dcmv = OrderedDict()
        if 0 not in stv.keys(): stv[0] = 0
        a = len(stv)
        for k in np.arange(a):
            l, u = _cms_mean(k)
            if k == 0:
                dcmv[k] = (0., 2.)
            elif k != a-1:
                dcmv[k] = (l/stv[k], u/stv[k])
            else:
                dcmv[k] = (l/stv[k], 10000.)
        return CMV(dcmv)

    def msl_to_mvl(stv):
        dmvl = OrderedDict()
        if 0 not in stv.keys(): stv[0] = 0
        a = len(stv)
        for k in np.arange(a):
            if k not in stv.keys(): stv[k] = 0
            l, u = _msl_mean(k)
            if k == 0:
                dmvl[k] = (0., 10.)
            elif k != a-1:
                dmvl[k] = (l/stv[k], u/stv[k])
            else:
                dmvl[k] = (l/stv[k], 10000.)
        return MVL(dmvl)
    
    return cms_to_cmv, msl_to_mvl


     
def get_co2_params(db):
    import re
    
    def get_param_values(x):
        m = re.findall('\((.+?)\)', x)
        d={}
        for i in m:
            k = re.findall("\'(.+?)\'", i)[0]
            v = re.findall("(?:value)=(.*),\sexpr", i)[0]
            d[k] = v
        return d
    db['co2_params_'] = db.apply(lambda x: get_param_values(x['co2_params_calibrated']), axis=1)

    for p in ['a', 'a2', 'b', 'b2', 'c', 'l', 'l2', 't', 'trg']:
        db[p] = db.apply(lambda x: eval(x['co2_params_'][p]), axis=1)
    
    a, a2, b, b2, c, l, l2, t, trg = db['a'], db['a2'], db['b'], db['b2'], db['c'], db['l'], db['l2'], db['t'], db['trg']
    
    eng = db['engine_type']
    gt = eng == 'gasoline turbo'
    na = eng == 'gasoline natural aspiration'
    di = eng == 'diesel'
    
    params = {
        'gasoline turbo': {
                'a': a[gt].mean(),
                'b': b[gt].mean(),
                'c': c[gt].mean(),
                'a2': a2[gt].mean(),
                'b2': b2[gt].mean(),
                'l': l[gt].mean(),
                'l2': l2[gt].mean(),
                't': t[gt].mean(),
                'trg': trg[gt].mean(),
                },
        'gasoline natural aspiration': {
                'a': a[na].mean(),
                'b': b[na].mean(),
                'c': c[na].mean(),
                'a2': a2[na].mean(),
                'b2': b2[na].mean(),
                'l': l[na].mean(),
                'l2': l2[na].mean(),
                't': t[na].mean(),
                'trg': trg[na].mean(),
                },
        'diesel': {
                'a': a[di].mean(),
                'b': b[di].mean(),
                'c': c[di].mean(),
                'a2': a2[di].mean(),
                'b2': b2[di].mean(),
                'l': l[di].mean(),
                'l2': l2[di].mean(),
                't': t[di].mean(),
                'trg': trg[di].mean(),
                }
        }
    
    return params      

def _calculate_stv(fd,rd,gbr):
    return {k:((1000*fd*v)/(60*2*3.14*rd)) for k, v in gbr.items()}


def heating_value(fuel):
    dic = {'gasoline': 43000,
           'diesel': 43600,
#            'E85': 29230,
           'LPG': 46000, #'LPG/CNG': 46000,
           'gas': 46000, #'LPG/CNG': 46000,
           'bioethanol': 38000, #'Biodiesel': 38000,
           }
    return dic[fuel]

def carbon_content(fuel):
    dic = {'gasoline': 3.153,
           'diesel': 3.153,
#             'E85': 2.093,
           'LPG': 3.014, #'LPG/CNG': 3.014, #for the CNG=2.75
           'gas': 3.014, #'LPG/CNG': 3.014, #for the CNG=2.75
           'bioethanol': 2.82, #'Biodiesel': 2.82,
           }
    return dic[fuel]

def get_measured_cars_db(f):
    d = pd.read_excel(f, sheetname='real_cars')
    d = d.reset_index().dropna(subset=['Car']).set_index('Car')
    
    _drop_cols = ['index','Unnamed: 1', 'delta_speed_cold', 'state_of_charge_balance',
                       'state_of_charge_balance_window', 'min_soc', 'max_soc', 
                       'alternator_charging_currents', 'Unnamed: 8', 'air_density',
                       'angle_slope', 'auxiliaries_torque_loss', 'k1', 'k2', 'k5']
    
    d = d.drop(_drop_cols, axis=1)
    col_names = d.columns
    new_col_names = []
    for c in col_names:
        s = c.split('.')
        if len(s) > 1: c = s[0]
        new_col_names.append(c)
    d.columns = new_col_names   
    return d
    
    
def _read_params_txt(f):
    p = []
    with open(f, 'r') as f:
        for line in f: p.append(line.split('\n')[0])
    
    return p

def _read_params(fr, fw):
    df = pd.read_excel(fr, sheetname='parameters', index_col=1, parse_cols=1)
    df = df.reset_index().dropna(subset=['INPUTS']).set_index('INPUTS')
    for i in df.index:
        if len(i.split()) > 1: df.drop(i, inplace=True)
        
    with open(fw, 'w') as f:
        f.writelines("%s\n" % item for item in df.index)
        
    return df
    
def _read_dtypes(f='data\dtypes.txt'):
    dtypes = {}
    with open(f, 'r') as f:
        for line in f: 
            key, value = line.split(':')
            dtypes[eval(key)] = eval(value)
 
    return dtypes

def _read_db(f, dtypes):
    df = pd.read_csv(f, header=[0, 1], skipinitialspace=True,
                     tupleize_cols=True, dtype=dtypes, encoding='latin',
                     na_values=['n.v.t.', 'n.a.', '-', 'n.b.', '#NAME?'])
    
    df.columns = pd.MultiIndex.from_tuples(df.columns, names = ['Category', 'Item'])
    df = df.drop(('Category', 'Item'), axis=1)
    
    return df



if __name__ == '__main__':
    main()