import pandas as pd, numpy as np
from pandalone import xleash
from datetime import datetime



shts = ['av_missing_powers_pos_pow',
        'co2_emission',
        'fuel_consumption',
        'max_power_required',
        'sufficient_power',
        'av_vel_pos_mov_pow',
        'av_pos_motive_powers',
        'sec_pos_mov_pow',
        'av_neg_motive_powers',
        'sec_neg_mov_pow',
        'av_pos_accelerations',
        'av_engine_speeds_out_pos_pow',
        'av_pos_engine_powers_out',
        'willans_a',
        'willans_b',
        'specific_fuel_consumption',
        'willans_efficiency',
        'time_percentage_pos_mov_pow',
        'time_percentage_neg_mov_pow']

refs = {'Ysc': "A2:A3",
        'SSc': "C2:D16",
        'theta': "F2:F16",
        'beta': "H2:H17",
        'gamma': "J2:J1281",
        'S': "L2:Z1281"}



def main():

    f = "MGT_par.xlsx"
    fin = "inKrig_MGT.csv"
    fout = "outKrig_MGT_Python.csv"

    dfins = pd.read_csv(fin)
#     dfins = dfins.ix[:1000, :]
    dfsplit = np.array_split(dfins, 100)

    sh = shts[0]
    dFL = pd.DataFrame(columns = shts)
    startTime = datetime.now()
    for sh in shts:
        ysc = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['Ysc'])))
        ssc = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['SSc'])))
        theta = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['theta'])))
        beta = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['beta'])))
        gamma = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['gamma'])))
        s = pd.DataFrame(xleash.lasso("%s#%s!%s"%(f, sh, refs['S'])))

        dS = pd.Series()
        for df in dfsplit:
            r = KrigingCo2mpas(df, ysc, ssc, theta, beta, gamma, s)
            dS = dS.append(r)
        
        dFL[sh] = dS
        print(datetime.now() - startTime)

    dFL.to_csv(fout, index=False)
    
    

def KrigingCo2mpas(x, ysc, ssc, theta, beta, gamma, s):
    """
    x is an array/dataframe with the following parameters:
    'Capacity', 'Mass', 'Driving', 'Transmission', 'Traction', 'SS', 'BERS',
    'MechLoad', 'AR', 'RR', 'Slope', 'T', 'P_C', 'AvgV', 'InitT'
    
    # Check inKrig_MGT.csv file
    """
    x = (x - ssc.ix[:,0].values) / ssc.ix[:,1].values
    
    sLL = x*beta.ix[1:, 0].values
    scalLossL = sLL.sum(axis=1) + beta.ix[0, 0]
    
    xtiled = np.tile(x, len(s)).reshape((-1, len(x.columns)))
    stiled = np.tile(s, (len(x), 1))

    corrFunFict = (stiled - xtiled)**2
    corrFunFict *= - theta.ix[:,0].values
    corrFun = np.exp(corrFunFict.sum(axis=1))
    corrFun = corrFun.reshape(len(x), len(s))
    
    sLR = corrFun*gamma.ix[:,0].values
    scalLossR = sLR.sum(axis=1)
    
    scalLoss= scalLossR + scalLossL

    return ysc.ix[0,0] + ysc.ix[1,0]*scalLoss



if __name__ == "__main__":
    main()
