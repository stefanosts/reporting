import pandas as pd, numpy as np
from os.path import join

dwd = r'D:\Work\datasets.git\green_driving' # Data working directory
c = '_results_conventionals.xlsx'
e = '_results_electrics.xlsx'

fc = join(dwd, c)
fe = join(dwd, e)

def main():

    dc = pd.read_excel(fc)[:29716]
    de = pd.read_excel(fe)[:29716]
    d = pd.concat([dc, de], axis=1, keys=['convs', 'elecs']).dropna()
    
    ft, et, gb = d.convs.fuel_type, d.convs.engine_is_turbo, d.convs.gear_box_type
    I_dt_at = (ft=='diesel') & (et==True) & (gb=='automatic')
    I_dt_mt = (ft=='diesel') & (et==True) & (gb=='manual')
    I_gt_at = (ft=='gasoline') & (et==True) & (gb=='automatic')
    I_gt_mt = (ft=='gasoline') & (et==True) & (gb=='manual')
    I_gna_at = (ft=='gasoline') & (et==False) & (gb=='automatic')
    I_gna_mt = (ft=='gasoline') & (et==False) & (gb=='manual')
    
    dt_at, dt_mt, gt_at, gt_mt, gna_at, gna_mt = \
        d[I_dt_at], d[I_dt_mt], d[I_gt_at], d[I_gt_mt], d[I_gna_at], d[I_gna_mt]
        
    cols = [('convs', 'angle_slope'),
            ('convs', 'auxiliaries_power_loss'),
            ('convs', 'engine_capacity'),
            ('convs', 'engine_max_power'),
            ('convs', 'f0'),
            ('convs', 'f1'),
            ('convs', 'f2'),
            ('convs', 'has_start_stop'),
            ('convs', 'n_wheel_drive'),
            ('convs', 'AvgV'),
            ('convs', 'InitT'),
            ('convs', 'vehicle_mass'),
            ('elecs', 'SOC'),
            ('convs', 'co2_emission'),
            ('convs', 'fuel_consumption'),
            ('convs', 'max_power_required'),
            ('elecs', 'CO2_EV'),
            ('elecs', 'CO2_Boost'),
            ('elecs', 'CO2_Smart_Charge'),
            ('elecs', 'i_tr_EV'),
            ('elecs', 'i_tr_Boost'),
            ('elecs', 'i_tr_smart_charge'),
            ('elecs', 'i_reg')]
    
    dt_at, dt_mt, gt_at, gt_mt, gna_at, gna_mt = \
        dt_at[cols], dt_mt[cols], gt_at[cols], gt_mt[cols], gna_at[cols], gna_mt[cols]
    
    dt_at.columns, dt_mt.columns, gt_at.columns, gt_mt.columns, gna_at.columns, gna_mt.columns = \
        dt_at.columns.droplevel(), dt_mt.columns.droplevel(), gt_at.columns.droplevel(), \
        gt_mt.columns.droplevel(), gna_at.columns.droplevel(), gna_mt.columns.droplevel()
        
    dt_at.to_csv(join(dwd, 'DT_AT.csv'), index=False); dt_mt.to_csv(join(dwd, 'DT_MT.csv'), index=False)
    gt_at.to_csv(join(dwd, 'GT_AT.csv'), index=False); gt_mt.to_csv(join(dwd, 'GT_MT.csv'), index=False)
    gna_at.to_csv(join(dwd, 'GNA_AT.csv'), index=False); gna_mt.to_csv(join(dwd, 'GNA_MT.csv'), index=False)



if __name__ == '__main__':
    main()
    
    

